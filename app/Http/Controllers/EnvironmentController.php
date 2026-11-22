<?php

namespace App\Http\Controllers;

use App\Actions\Sites\UpdateSiteEnvironmentAction;
use App\Enums\ActivityEvent;
use App\Http\Resources\ServerResource;
use App\Http\Resources\SiteResource;
use App\Models\ActivityLog;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Services\Ssh\SshService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EnvironmentController extends Controller
{
    public function show(Team $team, Server $server, Site $site, SshService $sshService): Response
    {
        $this->authorize('viewSecrets', $site);

        $site->load(['server']);

        $hasWorkers = $site->server->workers()->where('site_id', $site->id)->exists();

        return Inertia::render('sites/environment/show', [
            'server' => new ServerResource($server),
            'site' => new SiteResource($site),
            'has_workers' => $hasWorkers,
            'history' => $site->activityLogs()
                ->where('event', ActivityEvent::SiteEnvironmentUpdated)
                ->with('user:id,name')
                ->limit(50)
                ->get()
                ->map(fn (ActivityLog $entry): array => [
                    'id' => $entry->id,
                    'user_name' => $entry->user?->name,
                    'added_keys' => $entry->properties['added_keys'] ?? [],
                    'changed_keys' => $entry->properties['changed_keys'] ?? [],
                    'removed_keys' => $entry->properties['removed_keys'] ?? [],
                    'is_first_save' => (bool) ($entry->properties['first_save'] ?? false),
                    'created_at' => $entry->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'env_content' => Inertia::defer(function () use ($site, $sshService): string {
                if ($site->env_content !== null) {
                    return $site->env_content;
                }

                try {
                    $connection = $sshService->connect($site->server);
                    $envPath = $site->sharedPath().'/.env';
                    $content = $site->isIsolated()
                        ? $connection->exec($site->commandAsSystemUser('cat '.escapeshellarg($envPath)))
                        : $connection->download($envPath);
                    $connection->disconnect();

                    return $content;
                } catch (\Throwable) {
                    return '';
                }
            }),
        ]);
    }

    public function update(Request $request, Team $team, Server $server, Site $site, UpdateSiteEnvironmentAction $action): RedirectResponse
    {
        $this->authorize('changeDeploySettings', $site);

        $validated = $request->validate([
            'env_content' => ['required', 'string'],
            'clear_config_cache' => ['sometimes', 'boolean'],
            'restart_queue' => ['sometimes', 'boolean'],
        ]);

        $action->execute(
            $site,
            $validated['env_content'],
            $request->user(),
            clearConfigCache: (bool) ($validated['clear_config_cache'] ?? false),
            restartQueue: (bool) ($validated['restart_queue'] ?? false),
        );

        return redirect()
            ->back()
            ->with('success', 'Environment variables updated. Syncing to server...');
    }
}
