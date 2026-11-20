<?php

namespace App\Http\Controllers;

use App\Actions\SecurityAdvisories\RecordSecurityCheckAction;
use App\Enums\SecurityCheckStatus;
use App\Events\SecurityChecksUpdated;
use App\Jobs\CheckServerSecurityJob;
use App\Jobs\FixServerSecurityJob;
use App\Models\ServerSecurityCheck;
use App\Models\Team;
use App\Services\SecurityAdvisories\SecurityAdvisory;
use App\Services\SecurityAdvisories\SecurityAdvisoryCatalogue;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Security advisories published for the team's servers. Nothing runs on a
 * server until someone clicks: Check only reads, Fix installs the update.
 */
class SecurityUpdateController extends Controller
{
    public function index(Team $team, SecurityAdvisoryCatalogue $catalogue): Response
    {
        return Inertia::render('security-updates/index', [
            'advisories' => Inertia::live(
                fn (): array => $this->advisoriesFor($team, $catalogue),
                on: SecurityChecksUpdated::class,
                channel: new PrivateChannel("servers.{$team->id}"),
            ),
        ]);
    }

    public function check(Team $team, ServerSecurityCheck $securityCheck, RecordSecurityCheckAction $record): RedirectResponse
    {
        abort_unless($securityCheck->server->team_id === $team->id, 404);
        $this->authorize('refreshStatus', $securityCheck->server);

        if ($securityCheck->status->isBusy()) {
            return back()->with('error', 'This server is already being checked or updated.');
        }

        $record->execute($securityCheck, SecurityCheckStatus::Checking);
        CheckServerSecurityJob::dispatch($securityCheck);

        return back();
    }

    public function fix(Team $team, ServerSecurityCheck $securityCheck, RecordSecurityCheckAction $record): RedirectResponse
    {
        abort_unless($securityCheck->server->team_id === $team->id, 404);
        $this->authorize('update', $securityCheck->server);

        if (! $securityCheck->status->canBeFixed()) {
            return back()->with('error', 'Check the server first. It can only be updated once a check finds it needs the fix.');
        }

        $record->execute($securityCheck, SecurityCheckStatus::Fixing, [
            'detail' => null,
            'output' => null,
            'fixed_by' => auth()->id(),
        ]);
        FixServerSecurityJob::dispatch($securityCheck);

        return back()->with('success', "Installing the security update on {$securityCheck->server->name}.");
    }

    /**
     * Each advisory that affects any of the team's servers, with where each
     * of those servers stands.
     *
     * @return list<array<string, mixed>>
     */
    private function advisoriesFor(Team $team, SecurityAdvisoryCatalogue $catalogue): array
    {
        $checksByAdvisory = ServerSecurityCheck::query()
            ->whereIn('server_id', $team->servers()->select('id'))
            ->with(['server', 'fixedBy:id,name'])
            ->get()
            ->sortBy(fn (ServerSecurityCheck $check): string => $check->server->name)
            ->groupBy('advisory');

        return collect($catalogue->all())
            ->filter(fn (SecurityAdvisory $advisory): bool => $checksByAdvisory->has($advisory->key()))
            ->map(fn (SecurityAdvisory $advisory): array => [
                'key' => $advisory->key(),
                'title' => $advisory->title(),
                'severity' => $advisory->severity(),
                'published_on' => $advisory->publishedOn(),
                'summary' => $advisory->summary(),
                'fix_description' => $advisory->fixDescription(),
                'reference_url' => $advisory->referenceUrl(),
                'servers' => $this->serversFor($advisory, $checksByAdvisory->get($advisory->key())),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ServerSecurityCheck>  $checks
     * @return list<array<string, mixed>>
     */
    private function serversFor(SecurityAdvisory $advisory, Collection $checks): array
    {
        return $checks->map(fn (ServerSecurityCheck $check): array => [
            'id' => $check->id,
            'server' => [
                'id' => $check->server->id,
                'name' => $check->server->name,
                'ip_address' => $check->server->ip_address,
            ],
            'status' => $check->status->value,
            'status_label' => $check->status->label(),
            'status_color' => $check->status->color(),
            'is_busy' => $check->status->isBusy(),
            'can_fix' => $check->status->canBeFixed(),
            'detail' => $check->detail,
            'output' => $check->output,
            'checked_at' => $check->checked_at?->toIso8601String(),
            'fixed_at' => $check->fixed_at?->toIso8601String(),
            'fixed_by' => $check->fixedBy?->name,
            'manual_steps' => $advisory->manualSteps($check->server),
        ])->values()->all();
    }
}
