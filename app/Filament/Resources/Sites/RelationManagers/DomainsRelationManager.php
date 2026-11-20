<?php

namespace App\Filament\Resources\Sites\RelationManagers;

use App\Filament\RelationManagers\ReadOnlyRelationManager;
use App\Filament\Support\StatusBadge;
use App\Models\SiteDomain;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The site's domains and their certificates.
 */
class DomainsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'domains';

    protected static ?string $title = 'Domains and certificates';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('is_primary', 'desc')
            ->columns([
                TextColumn::make('hostname')->copyable(),
                TextColumn::make('type')->formatStateUsing(fn (SiteDomain $record): ?string => StatusBadge::label($record->type))->badge()->color('gray'),
                IconColumn::make('is_primary')->label('Primary')->boolean(),
                IconColumn::make('is_enabled')->label('On')->boolean(),
                StatusBadge::column('status'),
                TextColumn::make('verified_at')->label('DNS verified')->dateTime('j M Y')->placeholder('Not yet'),
                TextColumn::make('ssl_expires_at')
                    ->label('Certificate expires')
                    ->dateTime('j M Y')
                    ->color(fn (SiteDomain $record): ?string => $record->ssl_expires_at?->isBefore(now()->addDays(14)) ? 'danger' : null)
                    ->placeholder('No certificate'),
            ]);
    }
}
