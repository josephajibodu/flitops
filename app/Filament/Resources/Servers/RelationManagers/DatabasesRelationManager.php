<?php

namespace App\Filament\Resources\Servers\RelationManagers;

use App\Filament\RelationManagers\ReadOnlyRelationManager;
use App\Filament\Support\StatusBadge;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DatabasesRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'databases';

    protected static ?string $title = 'Databases';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('charset')->placeholder('–'),
                TextColumn::make('collation')->placeholder('–'),
                StatusBadge::column('status'),
                TextColumn::make('created_at')->label('Created')->dateTime('j M Y'),
            ]);
    }
}
