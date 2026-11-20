<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\Servers\ServerResource;
use App\Filament\Support\StatusBadge;
use App\Models\Server;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Servers belonging to a team or a user, each linking to its admin page.
 */
class ServersRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'servers';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('ip_address')->label('IP')->fontFamily('mono')->placeholder('–'),
                TextColumn::make('provider')->formatStateUsing(fn (Server $record): string => $record->provider->label()),
                StatusBadge::column('status'),
                TextColumn::make('created_at')->label('Created')->dateTime('j M Y'),
            ])
            ->recordUrl(fn (Server $record): string => ServerResource::getUrl('view', ['record' => $record]));
    }
}
