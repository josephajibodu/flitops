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

    public function getColumns(): int
    {
        return 2;
    }
}
