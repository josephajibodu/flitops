<?php

namespace App\Filament\Resources\Sites;

use App\Enums\SiteStatus;
use App\Filament\Resources\ReadOnlyResource;
use App\Filament\Resources\Servers\ServerResource;
use App\Filament\Resources\Sites\Pages\ListSites;
use App\Filament\Resources\Sites\Pages\ViewSite;
use App\Filament\Resources\Sites\RelationManagers\DeploymentsRelationManager;
use App\Filament\Resources\Sites\RelationManagers\DomainsRelationManager;
use App\Filament\Resources\Sites\RelationManagers\IncidentsRelationManager;
use App\Filament\Resources\Teams\TeamResource;
use App\Filament\Support\StatusBadge;
use App\Models\Site;
use App\Models\Team;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Every customer site, read-only. Environment variables and webhook secrets
 * are never shown.
 */
class SiteResource extends ReadOnlyResource
{
    protected static ?string $model = Site::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'domain';

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['domain', 'repository'];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Site')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('domain')->copyable(),
                        TextEntry::make('server.name')
                            ->label('Server')
                            ->url(fn (Site $record): string => ServerResource::getUrl('view', ['record' => $record->server])),
                        TextEntry::make('server.team.name')
                            ->label('Team')
                            ->url(fn (Site $record): ?string => $record->server->team ? TeamResource::getUrl('view', ['record' => $record->server->team]) : null),
                        StatusBadge::entry('status'),
                        TextEntry::make('error_message')
                            ->label('Error')
                            ->color('danger')
                            ->columnSpanFull()
                            ->visible(fn (Site $record): bool => filled($record->error_message)),
                        StatusBadge::entry('uptime_status')->label('Uptime')->placeholder('Not checked'),
                        TextEntry::make('uptime_checked_at')->label('Uptime last checked')->since()->placeholder('Never'),
                        TextEntry::make('project_type')->label('Project type')->formatStateUsing(fn (Site $record): ?string => StatusBadge::label($record->project_type))->placeholder('–'),
                        TextEntry::make('php_version')->label('PHP')->placeholder('–'),
                        TextEntry::make('root_path')->label('Folder on the server')->fontFamily('mono')->placeholder('–'),
                        TextEntry::make('system_user')->label('Runs as')->fontFamily('mono')->placeholder('–'),
                        TextEntry::make('created_at')->label('Created')->dateTime('j M Y, H:i'),
                    ]),
                Section::make('Repository')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('repository')->placeholder('None'),
                        TextEntry::make('repository_provider')->label('Provider')->formatStateUsing(fn (Site $record): ?string => StatusBadge::label($record->repository_provider))->placeholder('–'),
                        TextEntry::make('branch')->placeholder('–'),
                        IconEntry::make('auto_deploy')->label('Deploys on push')->boolean(),
                        TextEntry::make('build_command')->label('Build command')->fontFamily('mono')->placeholder('–'),
                        TextEntry::make('deployment_finished_at')->label('Last deployed')->dateTime('j M Y, H:i')->placeholder('Never'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('10s')
            ->columns([
                TextColumn::make('domain')->searchable()->sortable(),
                TextColumn::make('server.team.name')->label('Team'),
                TextColumn::make('server.name')->label('Server')->searchable(),
                StatusBadge::column('status'),
                StatusBadge::column('uptime_status')->label('Uptime')->placeholder('–'),
                TextColumn::make('deployment_finished_at')->label('Last deployed')->since()->sortable()->placeholder('Never'),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(SiteStatus::cases())->mapWithKeys(fn (SiteStatus $status): array => [$status->value => $status->label()])),
                SelectFilter::make('team')
                    ->options(fn (): array => Team::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $teamId): Builder => $query->whereHas('server', fn (Builder $server): Builder => $server->where('team_id', $teamId)),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DomainsRelationManager::class,
            DeploymentsRelationManager::class,
            IncidentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSites::route('/'),
            'view' => ViewSite::route('/{record}'),
        ];
    }
}
