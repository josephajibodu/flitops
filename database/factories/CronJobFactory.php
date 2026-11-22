<?php

namespace Database\Factories;

use App\Enums\CronJobStatus;
use App\Models\CronJob;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CronJob>
 */
class CronJobFactory extends Factory
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
            'site_id' => null,
            'command' => 'php /var/www/html/artisan schedule:run',
            'user' => config('server.user', 'deploy'),
            'frequency' => '* * * * *',
            'hidden' => false,
            'status' => CronJobStatus::Active,
        ];
    }

    public function forSite(Site $site): static
    {
        return $this->state(fn (array $attributes) => [
            'server_id' => $site->server_id,
            'site_id' => $site->id,
        ]);
    }
}
