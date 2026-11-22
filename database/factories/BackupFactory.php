<?php

namespace Database\Factories;

use App\Enums\BackupStatus;
use App\Models\Backup;
use App\Models\ServerDatabase;
use App\Models\Site;
use App\Models\StorageProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Backup>
 */
class BackupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_database_id' => ServerDatabase::factory(),
            'storage_provider_id' => StorageProvider::factory(),
            'status' => BackupStatus::Pending,
            'triggered_by' => 'manual',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BackupStatus::Completed,
            'storage_path' => 'backups/'.fake()->uuid().'.sql.gz',
            'size_bytes' => fake()->numberBetween(1024, 1024 * 1024 * 50),
            'verified_at' => now(),
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BackupStatus::Failed,
            'error_message' => 'Something went wrong.',
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'triggered_by' => 'scheduled',
        ]);
    }

    /**
     * Target a site's SQLite database instead of a server database.
     */
    public function forSite(Site $site): static
    {
        return $this->state(fn (array $attributes) => [
            'server_database_id' => null,
            'site_id' => $site->id,
        ]);
    }
}
