<?php

namespace App\Filament\Widgets;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Counts across every team.
 */
class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $serversInError = Server::query()->where('status', ServerStatus::Error)->count();

        return [
            Stat::make('Teams', number_format(Team::query()->count())),
            Stat::make('Users', number_format(User::query()->count())),
            Stat::make('Servers', number_format(Server::query()->count()))
                ->description(number_format(Server::query()->where('status', ServerStatus::Active)->count()).' active · '.number_format($serversInError).' in error')
                ->color($serversInError > 0 ? 'danger' : 'gray'),
            Stat::make('Sites', number_format(Site::query()->count())),
        ];
    }
}
