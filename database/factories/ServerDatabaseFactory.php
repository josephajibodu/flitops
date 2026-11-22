<?php

namespace Database\Factories;

use App\Enums\DatabaseStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerDatabase>
 */
class ServerDatabaseFactory extends Factory
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
            'name' => fake()->unique()->slug(2).'_'.fake()->randomNumber(4),
            'collation' => 'utf8mb4_unicode_ci',
            'charset' => 'utf8mb4',
            'status' => DatabaseStatus::Ready,
        ];
    }
}
