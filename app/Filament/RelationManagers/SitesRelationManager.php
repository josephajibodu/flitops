<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\Sites\SiteResource;
use App\Filament\Support\StatusBadge;
use App\Models\Site;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Sites on a server or in a team, each linking to its admin page.
 */
class SitesRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'sites';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('domain')->searchable(),
                TextColumn::make('server.name')->label('Server'),
                StatusBadge::column('status'),
                StatusBadge::column('uptime_status')->label('Uptime')->placeholder('–'),
                TextColumn::make('deployment_finished_at')->label('Last deployed')->since()->placeholder('Never'),
            ])
            ->recordUrl(fn (Site $record): string => SiteResource::getUrl('view', ['record' => $record]));
    }
}
