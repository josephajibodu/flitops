<?php

namespace Database\Factories;

use App\Enums\AdminAction;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAuditLog>
 */
class AdminAuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->platformAdmin(),
            'action' => AdminAction::PublishedSecurityAdvisory,
            'properties' => ['advisory' => '2026-11-nginx2root', 'target' => 'all', 'teams' => 1, 'servers' => 1],
            'ip_address' => fake()->ipv4(),
        ];
    }
}
