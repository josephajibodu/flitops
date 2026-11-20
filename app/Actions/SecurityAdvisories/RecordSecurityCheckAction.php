<?php

namespace App\Actions\SecurityAdvisories;

use App\Enums\SecurityCheckStatus;
use App\Events\SecurityChecksUpdated;
use App\Models\ServerSecurityCheck;
use App\Notifications\SecurityAdvisoryPublished;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Save where a server stands on an advisory and tell the page. Once every
 * affected server in the team is protected, the team's banner for the
 * advisory is cleared.
 */
class RecordSecurityCheckAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(ServerSecurityCheck $check, SecurityCheckStatus $status, array $attributes = []): void
    {
        $check->update(['status' => $status, ...$attributes]);

        if ($status === SecurityCheckStatus::Covered) {
            $this->clearAlertWhenTeamIsProtected($check);
        }

        broadcast(new SecurityChecksUpdated($check->server->team_id));
    }

    private function clearAlertWhenTeamIsProtected(ServerSecurityCheck $check): void
    {
        $team = $check->server->team;

        $stillExposed = ServerSecurityCheck::query()
            ->where('advisory', $check->advisory)
            ->whereIn('server_id', $team->servers()->select('id'))
            ->where('status', '!=', SecurityCheckStatus::Covered)
            ->exists();

        if ($stillExposed) {
            return;
        }

        $target = SecurityAdvisoryPublished::targetKey($team, $check->advisory);

        DatabaseNotification::query()
            ->where('type', SecurityAdvisoryPublished::class)
            ->whereNull('read_at')
            ->get()
            ->filter(fn (DatabaseNotification $notification): bool => ($notification->data['target'] ?? null) === $target)
            ->markAsRead();
    }
}
