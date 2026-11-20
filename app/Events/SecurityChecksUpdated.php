<?php

namespace App\Events;

use App\Concerns\QueuesBroadcasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A security check or fix on one of the team's servers started, finished or
 * failed.
 */
class SecurityChecksUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, QueuesBroadcasts;

    public function __construct(
        public int $teamId,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('servers.'.$this->teamId),
        ];
    }

    /**
     * @return array{team_id: int}
     */
    public function broadcastWith(): array
    {
        return [
            'team_id' => $this->teamId,
        ];
    }

    public function broadcastAs(): string
    {
        return 'security.checks.updated';
    }
}
