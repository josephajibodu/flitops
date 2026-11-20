<?php

namespace App\Filament\Resources\Servers\RelationManagers;

use App\Filament\RelationManagers\ReadOnlyRelationManager;
use App\Filament\Support\StatusBadge;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkersRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'workers';

    protected static ?string $title = 'Workers';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->placeholder('–'),
                TextColumn::make('site.domain')->label('Site')->placeholder('Server-wide'),
                TextColumn::make('command')->fontFamily('mono')->wrap(),
                TextColumn::make('user'),
                TextColumn::make('numprocs')->label('Processes')->alignEnd(),
                StatusBadge::column('status'),
            ]);
    }
}
