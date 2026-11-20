<?php

namespace App\Filament\Resources\Teams\RelationManagers;

use App\Filament\RelationManagers\ReadOnlyRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Models\TeamRole;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The team's members and the role each has in it. The owner is shown on the
 * team itself.
 */
class MembersRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Members';

    public function table(Table $table): Table
    {
        $roleNames = TeamRole::query()->pluck('name', 'id');

        return $table
            ->recordTitleAttribute('email')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')
                    ->state(fn (User $record): ?string => $roleNames[$record->pivot->team_role_id] ?? null)
                    ->badge()
                    ->color('gray'),
                TextColumn::make('pivot.created_at')->label('Joined')->dateTime('j M Y'),
            ])
            ->recordUrl(fn (User $record): string => UserResource::getUrl('view', ['record' => $record]));
    }
}
