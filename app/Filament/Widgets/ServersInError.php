<?php

namespace App\Filament\Widgets;

use App\Enums\ServerStatus;
use App\Filament\Resources\Servers\ServerResource;
use App\Models\Server;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Servers whose provisioning failed or that are otherwise in error.
 */
class ServersInError extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Servers in error';

    public function table(Table $table): Table
    {
        return $table
            ->query(Server::query()->with('team')->where('status', ServerStatus::Error))
            ->defaultSort('updated_at', 'desc')
            ->poll('30s')
            ->paginated([5])
            ->emptyStateHeading('No servers in error')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('team.name')->label('Team'),
                TextColumn::make('error_message')->label('Error')->limit(80)->wrap()->placeholder('–'),
                TextColumn::make('updated_at')->label('Since')->since(),
            ])
            ->recordUrl(fn (Server $record): string => ServerResource::getUrl('view', ['record' => $record]));
    }
}
