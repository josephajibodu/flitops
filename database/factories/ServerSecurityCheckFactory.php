<?php

namespace Database\Factories;

use App\Enums\SecurityCheckStatus;
use App\Models\Server;
use App\Models\ServerSecurityCheck;
use App\Services\SecurityAdvisories\Nginx2RootAdvisory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerSecurityCheck>
 */
class ServerSecurityCheckFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'advisory' => Nginx2RootAdvisory::KEY,
            'status' => SecurityCheckStatus::Unchecked,
        ];
    }

    public function status(SecurityCheckStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
