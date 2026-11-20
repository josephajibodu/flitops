<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\RelationManagers\ReadOnlyRelationManager;
use App\Filament\Resources\Teams\TeamResource;
use App\Models\Team;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The teams this person belongs to and their role in each.
 */
class TeamsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'teams';

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->description(fn (Team $record): string => $record->slug),
                TextColumn::make('role')
                    ->state(fn (Team $record): ?string => $user->teamRole($record)?->name)
                    ->badge()
                    ->color('gray'),
                TextColumn::make('owner.email')->label('Owner'),
                TextColumn::make('pivot.created_at')->label('Joined')->dateTime('j M Y'),
            ])
            ->recordUrl(fn (Team $record): string => TeamResource::getUrl('view', ['record' => $record]));
    }
}
