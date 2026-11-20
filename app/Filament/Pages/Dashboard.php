<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The admin's front page: how big Flitops is and what broke recently.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Overview';

    protected static ?string $navigationLabel = 'Overview';

    public function getHeading(): string
    {
        $greeting = match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        $firstName = str(auth()->user()?->name)->before(' ')->toString();

        return $firstName === '' ? $greeting : "{$greeting}, {$firstName}";
    }

    public function getSubheading(): string
    {
        return 'Here\'s how Flitops customers\' servers and sites are doing.';
    }

    public function getColumns(): int
    {
        return 2;
    }
}
