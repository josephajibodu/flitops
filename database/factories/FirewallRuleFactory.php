<?php

namespace Database\Factories;

use App\Enums\FirewallRuleStatus;
use App\Models\FirewallRule;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FirewallRule>
 */
class FirewallRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->slug(2),
            'server_id' => Server::factory(),
            'type' => 'inbound',
            'protocol' => 'tcp',
            'port' => (string) fake()->randomElement([22, 80, 443, 3306]),
            'source' => '0.0.0.0/0',
            'mask' => null,
            'note' => null,
            'status' => FirewallRuleStatus::Active,
        ];
    }
}
