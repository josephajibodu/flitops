<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\ReadOnlyResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\TeamsRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Every person with a Flitops account, read-only.
 */
class UserResource extends ReadOnlyResource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'email';

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email')->copyable(),
                        TextEntry::make('plan')->formatStateUsing(fn (User $record): string => $record->plan->label())->badge()->color('gray'),
                        TextEntry::make('email_verified_at')->label('Email verified')->dateTime('j M Y, H:i')->placeholder('Not verified'),
                        IconEntry::make('two_factor')->label('Two-factor sign-in')->state(fn (User $record): bool => $record->hasEnabledTwoFactorAuthentication())->boolean(),
                        TextEntry::make('servers')->label('Servers they own')->state(fn (User $record): string => $record->ownedServerCount().' of '.($record->plan->serverLimit() ?? 'unlimited')),
                        TextEntry::make('currentTeam.name')->label('Current team')->placeholder('–'),
                        IconEntry::make('is_admin')->label('Flitops staff')->boolean(),
                        TextEntry::make('created_at')->label('Signed up')->dateTime('j M Y, H:i'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('plan')->formatStateUsing(fn (User $record): string => $record->plan->label())->badge()->color('gray'),
                IconColumn::make('email_verified_at')->label('Verified')->boolean(),
                IconColumn::make('two_factor_confirmed_at')->label('2FA')->boolean(),
                TextColumn::make('teams_count')->label('Teams')->counts('teams')->alignEnd(),
                TextColumn::make('created_at')->label('Signed up')->dateTime('j M Y')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_admin')->label('Flitops staff'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TeamsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
