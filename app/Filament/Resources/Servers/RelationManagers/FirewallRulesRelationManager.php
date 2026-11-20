<?php

namespace App\Filament\Resources\Servers\RelationManagers;

use App\Filament\RelationManagers\ReadOnlyRelationManager;
use App\Filament\Support\StatusBadge;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FirewallRulesRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'firewallRules';

    protected static ?string $title = 'Firewall rules';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('type')->badge()->color('gray'),
                TextColumn::make('protocol')->placeholder('–'),
                TextColumn::make('port')->fontFamily('mono')->placeholder('Any'),
                TextColumn::make('source')->fontFamily('mono')->placeholder('Anywhere'),
                StatusBadge::column('status'),
            ]);
    }
}
