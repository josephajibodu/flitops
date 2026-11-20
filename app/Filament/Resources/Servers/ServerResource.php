<?php

namespace App\Filament\Resources\Servers;

use App\Enums\Provider;
use App\Enums\ServerStatus;
use App\Filament\RelationManagers\SitesRelationManager;
use App\Filament\Resources\ReadOnlyResource;
use App\Filament\Resources\Servers\Pages\ListServers;
use App\Filament\Resources\Servers\Pages\ViewServer;
use App\Filament\Resources\Servers\RelationManagers\CronJobsRelationManager;
use App\Filament\Resources\Servers\RelationManagers\DatabasesRelationManager;
use App\Filament\Resources\Servers\RelationManagers\FirewallRulesRelationManager;
use App\Filament\Resources\Servers\RelationManagers\ProvisioningLogsRelationManager;
use App\Filament\Resources\Servers\RelationManagers\SecurityChecksRelationManager;
use App\Filament\Resources\Servers\RelationManagers\ServicesRelationManager;
use App\Filament\Resources\Servers\RelationManagers\WorkersRelationManager;
use App\Filament\Resources\Teams\TeamResource;
use App\Filament\Support\StatusBadge;
use App\Models\Server;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Every customer server, read-only: what it is, where it runs, what state
 * it's in and what's on it. Secrets (sudo and database passwords, keys,
 * provider tokens) are never shown.
 */
class ServerResource extends ReadOnlyResource
{
    protected static ?string $model = Server::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'ip_address'];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Server')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('team.name')
                            ->label('Team')
                            ->url(fn (Server $record): ?string => $record->team ? TeamResource::getUrl('view', ['record' => $record->team]) : null),
                        TextEntry::make('user.email')->label('Created by')->placeholder('–'),
                        StatusBadge::entry('status'),
                        TextEntry::make('error_message')
                            ->label('Error')
                            ->color('danger')
                            ->columnSpanFull()
                            ->visible(fn (Server $record): bool => filled($record->error_message)),
                        TextEntry::make('provider')->formatStateUsing(fn (Server $record): string => $record->provider->label()),
                        TextEntry::make('region')->placeholder('–'),
                        TextEntry::make('size')->placeholder('–'),
                        TextEntry::make('provider_server_id')->label('Provider\'s ID')->fontFamily('mono')->copyable()->placeholder('–'),
                        TextEntry::make('ip_address')->label('Public IP')->fontFamily('mono')->copyable()->placeholder('–'),
                        TextEntry::make('private_ip_address')->label('Private IP')->fontFamily('mono')->placeholder('–'),
                        TextEntry::make('ssh_port')->label('SSH port')->placeholder('22'),
                        StatusBadge::entry('connection_status')->label('Connection'),
                    ]),
                Section::make('Software')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('type')->formatStateUsing(fn (Server $record): ?string => StatusBadge::label($record->type))->placeholder('–'),
                        TextEntry::make('ubuntu_version')->label('Ubuntu')->placeholder('–'),
                        TextEntry::make('php_version')->label('PHP')->placeholder('–'),
                        TextEntry::make('web_server')->label('Web server')->placeholder('–'),
                        TextEntry::make('database_type')->label('Database')->placeholder('–'),
                        TextEntry::make('node_version')->label('Node')->formatStateUsing(fn (Server $record): ?string => StatusBadge::label($record->node_version))->placeholder('–'),
                        TextEntry::make('timezone')->placeholder('–'),
                    ]),
                Section::make('History')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('created_at')->label('Created')->dateTime('j M Y, H:i'),
                        TextEntry::make('provisioned_at')->label('Provisioned')->dateTime('j M Y, H:i')->placeholder('Not yet'),
                        TextEntry::make('last_ssh_connection_at')->label('Last connected')->since()->placeholder('Never'),
                        TextEntry::make('notes')->placeholder('–'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('10s')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('team.name')->label('Team')->searchable(),
                TextColumn::make('provider')->formatStateUsing(fn (Server $record): string => collect([$record->provider->label(), $record->region])->filter()->implode(' · ')),
                TextColumn::make('ip_address')->label('IP')->fontFamily('mono')->searchable()->placeholder('–'),
                StatusBadge::column('status'),
                StatusBadge::column('connection_status')->label('Connection'),
                TextColumn::make('created_at')->label('Created')->dateTime('j M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(ServerStatus::cases())->mapWithKeys(fn (ServerStatus $status): array => [$status->value => $status->label()])),
                SelectFilter::make('provider')->options(collect(Provider::cases())->mapWithKeys(fn (Provider $provider): array => [$provider->value => $provider->label()])),
                SelectFilter::make('team')->relationship('team', 'name')->searchable()->preload(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SitesRelationManager::class,
            DatabasesRelationManager::class,
            WorkersRelationManager::class,
            CronJobsRelationManager::class,
            FirewallRulesRelationManager::class,
            ServicesRelationManager::class,
            ProvisioningLogsRelationManager::class,
            SecurityChecksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServers::route('/'),
            'view' => ViewServer::route('/{record}'),
        ];
    }
}
