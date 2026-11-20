<?php

namespace App\Filament\Resources\Deployments;

use App\Enums\DeploymentStatus;
use App\Filament\Resources\Deployments\Pages\ListDeployments;
use App\Filament\Resources\Deployments\Pages\ViewDeployment;
use App\Filament\Resources\ReadOnlyResource;
use App\Filament\Resources\Sites\SiteResource;
use App\Filament\Support\StatusBadge;
use App\Models\Deployment;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Deployments across every site, failed ones first, with their logs.
 */
class DeploymentResource extends ReadOnlyResource
{
    protected static ?string $model = Deployment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static string|UnitEnum|null $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 3;

    /**
     * A deployment's page is titled with the site it went to.
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Deployment ? "Deployment to {$record->site->domain}" : 'Deployment';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Deployment')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('site.domain')
                            ->label('Site')
                            ->url(fn (Deployment $record): string => SiteResource::getUrl('view', ['record' => $record->site])),
                        TextEntry::make('site.server.team.name')->label('Team'),
                        StatusBadge::entry('status'),
                        TextEntry::make('triggered_by')->label('Started by')->formatStateUsing(fn (Deployment $record): string => self::startedBy($record)),
                        TextEntry::make('commit_hash')->label('Commit')->formatStateUsing(fn (?string $state): ?string => $state ? Str::limit($state, 7, '') : null)->fontFamily('mono')->copyable()->placeholder('–'),
                        TextEntry::make('commit_message')->label('Message')->columnSpan(3)->placeholder('–'),
                        TextEntry::make('started_at')->label('Started')->dateTime('j M Y, H:i:s')->placeholder('–'),
                        TextEntry::make('finished_at')->label('Finished')->dateTime('j M Y, H:i:s')->placeholder('–'),
                        TextEntry::make('duration_seconds')->label('Took')->formatStateUsing(fn (?int $state): ?string => $state === null ? null : $state.'s')->placeholder('–'),
                    ]),
                Section::make('Log')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('log')
                            ->hiddenLabel()
                            ->state(fn (Deployment $record): string => $record->logs()->orderBy('id')->pluck('message')->implode("\n"))
                            ->extraAttributes(['class' => 'flitops-log'])
                            ->placeholder('Nothing logged.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('10s')
            ->columns(self::columns())
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(DeploymentStatus::cases())->mapWithKeys(fn (DeploymentStatus $status): array => [$status->value => $status->label()]))
                    ->default(DeploymentStatus::Failed->value),
            ]);
    }

    /**
     * The deployment columns, which a site's own deployments tab shows too.
     *
     * @return list<TextColumn>
     */
    public static function columns(bool $withSite = true): array
    {
        return array_values(array_filter([
            $withSite ? TextColumn::make('site.domain')->label('Site')->searchable() : null,
            $withSite ? TextColumn::make('site.server.team.name')->label('Team') : null,
            TextColumn::make('commit_message')
                ->label('Commit')
                ->description(fn (Deployment $record): ?string => $record->commit_hash ? Str::limit($record->commit_hash, 7, '') : null)
                ->limit(60)
                ->placeholder('–'),
            StatusBadge::column('status'),
            TextColumn::make('triggered_by')->label('Started by')->formatStateUsing(fn (Deployment $record): string => self::startedBy($record)),
            TextColumn::make('created_at')->label('When')->since()->sortable(),
            TextColumn::make('duration_seconds')->label('Took')->formatStateUsing(fn (?int $state): ?string => $state === null ? null : $state.'s')->alignEnd()->placeholder('–'),
        ]));
    }

    private static function startedBy(Deployment $deployment): string
    {
        return $deployment->user?->email ?? Str::headline((string) $deployment->triggered_by ?: 'Flitops');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeployments::route('/'),
            'view' => ViewDeployment::route('/{record}'),
        ];
    }
}
