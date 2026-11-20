<?php

namespace App\Filament\Resources\Teams\RelationManagers;

use App\Filament\RelationManagers\ReadOnlyRelationManager;
use App\Models\ActivityLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The team's own activity log, the same one its members see.
 */
class ActivityRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'activityLogs';

    protected static ?string $title = 'Activity';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, H:i'),
                TextColumn::make('user.email')->label('Who')->placeholder('Flitops'),
                TextColumn::make('event')->label('What')->formatStateUsing(fn (ActivityLog $record): string => $record->event->label()),
            ]);
    }
}
