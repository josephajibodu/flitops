<?php

namespace App\Filament\Resources\Sites\RelationManagers;

use App\Filament\RelationManagers\ReadOnlyRelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Times the uptime check found the site down.
 */
class IncidentsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'incidents';

    protected static ?string $title = 'Downtime';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('started_at')->label('Went down')->dateTime('j M Y, H:i'),
                TextColumn::make('resolved_at')->label('Back up')->dateTime('j M Y, H:i')->placeholder('Still down'),
                TextColumn::make('reason')->wrap()->placeholder('–'),
            ]);
    }
}
