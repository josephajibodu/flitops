<?php

namespace App\Filament\Resources\Teams;

use App\Filament\RelationManagers\ServersRelationManager;
use App\Filament\RelationManagers\SitesRelationManager;
use App\Filament\Resources\ReadOnlyResource;
use App\Filament\Resources\Teams\Pages\ListTeams;
use App\Filament\Resources\Teams\Pages\ViewTeam;
use App\Filament\Resources\Teams\RelationManagers\ActivityRelationManager;
use App\Filament\Resources\Teams\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Models\Team;
use BackedEnum;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Every customer team, read-only: who's in it and what it runs.
 */
class TeamResource extends ReadOnlyResource
{
    protected static ?string $model = Team::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug'];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Team')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug')->label('Handle')->copyable()->fontFamily('mono'),
                        TextEntry::make('owner.email')
                            ->label('Owner')
                            ->url(fn (Team $record): ?string => $record->owner ? UserResource::getUrl('view', ['record' => $record->owner]) : null),
                        TextEntry::make('plan')->label('Plan (the owner\'s)')->state(fn (Team $record): ?string => $record->owner?->plan->label())->badge()->color('gray'),
                        IconEntry::make('personal_team')->label('Personal team')->boolean(),
                        TextEntry::make('created_at')->label('Created')->dateTime('j M Y, H:i'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->description(fn (Team $record): string => $record->slug)
                    ->searchable(['name', 'slug'])
                    ->sortable(),
                TextColumn::make('owner.email')->label('Owner')->searchable(),
                TextColumn::make('plan')->state(fn (Team $record): ?string => $record->owner?->plan->label())->badge()->color('gray'),
                TextColumn::make('users_count')->label('Members')->counts('users')->sortable()->alignEnd(),
                TextColumn::make('servers_count')->label('Servers')->counts('servers')->sortable()->alignEnd(),
                IconColumn::make('personal_team')->label('Personal')->boolean()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Created')->dateTime('j M Y')->sortable(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MembersRelationManager::class,
            ServersRelationManager::class,
            SitesRelationManager::class,
            ActivityRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeams::route('/'),
            'view' => ViewTeam::route('/{record}'),
        ];
    }
}
