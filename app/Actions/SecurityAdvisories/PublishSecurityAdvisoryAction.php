<?php

namespace App\Actions\SecurityAdvisories;

use App\Enums\SecurityCheckStatus;
use App\Enums\TeamPermission;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Notifications\SecurityAdvisoryPublished;
use App\Services\SecurityAdvisories\SecurityAdvisory;
use Illuminate\Support\Collection;

/**
 * Put an advisory in front of every team with a server it may affect: each
 * such server gets an unchecked entry, and the people who can manage the
 * team's servers get an email and a banner. Nothing runs on any server.
 *
 * Safe to run again: servers already listed are left alone, and a team is
 * only told again when it has newly affected servers.
 */
class PublishSecurityAdvisoryAction
{
    /**
     * @return array{teams: int, servers: int}
     */
    public function execute(SecurityAdvisory $advisory): array
    {
        $teamsNotified = 0;
        $serversAdded = 0;

        Server::query()
            ->with('team')
            ->whereNotNull('team_id')
            ->get()
            ->filter(fn (Server $server): bool => $advisory->appliesTo($server))
            ->groupBy('team_id')
            ->each(function (Collection $servers) use ($advisory, &$teamsNotified, &$serversAdded): void {
                $newlyListed = $servers->filter(fn (Server $server): bool => $server->securityChecks()
                    ->firstOrCreate(['advisory' => $advisory->key()], ['status' => SecurityCheckStatus::Unchecked])
                    ->wasRecentlyCreated);

                if ($newlyListed->isEmpty()) {
                    return;
                }

                $team = $servers->first()->team;
                $notification = new SecurityAdvisoryPublished($team, $advisory, $servers->count());

                $this->peopleWhoManageServers($team)->each(fn (User $user) => $user->notify($notification));

                $teamsNotified++;
                $serversAdded += $newlyListed->count();
            });

        return ['teams' => $teamsNotified, 'servers' => $serversAdded];
    }

    /**
     * @return Collection<int, User>
     */
    private function peopleWhoManageServers(Team $team): Collection
    {
        return $team->users()->get()
            ->push($team->owner)
            ->filter()
            ->unique('id')
            ->filter(fn (User $user): bool => $user->hasTeamPermission($team, TeamPermission::ManageServers))
            ->values();
    }
}
