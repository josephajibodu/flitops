<?php

namespace App\Filament\Resources\Servers\RelationManagers;

use App\Filament\RelationManagers\ReadOnlyRelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProvisioningLogsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'provisioningLogs';

    protected static ?string $title = 'Provisioning log';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, H:i:s'),
                TextColumn::make('type')->badge()->color(fn (?string $state): string => $state === 'error' ? 'danger' : 'gray'),
                TextColumn::make('message')->extraAttributes(['class' => 'flitops-log'])->wrap(),
            ]);
    }
}
