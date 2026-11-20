<?php

namespace App\Filament\Resources\Servers\RelationManagers;

use App\Filament\Pages\SecurityAdvisoryServers;
use App\Filament\RelationManagers\ReadOnlyRelationManager;
use App\Filament\Support\StatusBadge;
use App\Models\ServerSecurityCheck;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Where this server stands on each security advisory it was listed for.
 */
class SecurityChecksRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'securityChecks';

    protected static ?string $title = 'Security updates';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('advisory')
                    ->formatStateUsing(fn (ServerSecurityCheck $record): string => $record->securityAdvisory()->title())
                    ->description(fn (ServerSecurityCheck $record): string => $record->advisory),
                StatusBadge::column('status'),
                TextColumn::make('detail')->wrap()->placeholder('–'),
                TextColumn::make('checked_at')->label('Last checked')->since()->placeholder('Never'),
                TextColumn::make('fixed_at')->label('Fixed by Flitops')->dateTime('j M Y, H:i')->placeholder('–'),
            ])
            ->recordUrl(fn (ServerSecurityCheck $record): string => SecurityAdvisoryServers::getUrl(['advisory' => $record->advisory]));
    }
}
