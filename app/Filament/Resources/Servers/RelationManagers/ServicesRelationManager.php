<?php

namespace App\Filament\Resources\Servers\RelationManagers;

use App\Filament\RelationManagers\ReadOnlyRelationManager;
use App\Filament\Support\StatusBadge;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServicesRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'installedServices';

    protected static ?string $title = 'Services';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('version')->placeholder('–'),
                TextColumn::make('installed_version')->label('Installed')->placeholder('–'),
                StatusBadge::column('status'),
            ]);
    }
}
