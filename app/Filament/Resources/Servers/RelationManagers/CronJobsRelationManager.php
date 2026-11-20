<?php

namespace App\Filament\Resources\Servers\RelationManagers;

use App\Filament\RelationManagers\ReadOnlyRelationManager;
use App\Filament\Support\StatusBadge;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CronJobsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'cronJobs';

    protected static ?string $title = 'Cron jobs';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('command')->fontFamily('mono')->wrap(),
                TextColumn::make('frequency')->fontFamily('mono'),
                TextColumn::make('user'),
                TextColumn::make('site.domain')->label('Site')->placeholder('Server-wide'),
                StatusBadge::column('status'),
            ]);
    }
}
