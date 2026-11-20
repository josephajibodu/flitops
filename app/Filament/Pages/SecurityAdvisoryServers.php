<?php

namespace App\Filament\Pages;

use App\Actions\SecurityAdvisories\PublishSecurityAdvisoryAction;
use App\Enums\AdminAction;
use App\Enums\SecurityCheckStatus;
use App\Filament\Resources\Servers\ServerResource;
use App\Filament\Resources\Teams\TeamResource;
use App\Filament\Support\StatusBadge;
use App\Models\AdminAuditLog;
use App\Models\Server;
use App\Models\ServerSecurityCheck;
use App\Models\Team;
use App\Services\SecurityAdvisories\SecurityAdvisory;
use App\Services\SecurityAdvisories\SecurityAdvisoryCatalogue;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * One security advisory: where every listed server stands on it, and the
 * Publish button that lists more servers and tells their teams. Publishing
 * never runs anything on a server; customers check and fix from their own
 * Security updates page.
 */
class SecurityAdvisoryServers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    public string $advisory;

    public static function getRoutePath(Panel $panel): string
    {
        return '/security-advisories/{advisory}';
    }

    public function mount(string $advisory, SecurityAdvisoryCatalogue $catalogue): void
    {
        abort_unless($catalogue->has($advisory), 404);

        $this->advisory = $advisory;
    }

    public function getTitle(): string
    {
        return $this->securityAdvisory()->title();
    }

    public function getSubheading(): string
    {
        $advisory = $this->securityAdvisory();

        return Str::ucfirst($advisory->severity()).' · written '.$advisory->publishedOn().' · '.$advisory->summary();
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [SecurityAdvisories::getUrl() => 'Security advisories', $this->advisory];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Publish')
                ->icon(Heroicon::OutlinedMegaphone)
                ->modalHeading('Publish this advisory')
                ->modalDescription('Servers it may affect are listed on their team\'s Security updates page, and the people who manage those servers get an email. Servers already listed are left alone, and nothing runs on any server.')
                ->modalSubmitActionLabel('Publish')
                ->schema([
                    Radio::make('target')
                        ->label('Send to')
                        ->options([
                            'all' => 'Every server it may affect',
                            'teams' => 'Chosen teams',
                            'servers' => 'Chosen servers',
                        ])
                        ->default('all')
                        ->required()
                        ->live(),
                    Select::make('teams')
                        ->label('Teams')
                        ->multiple()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Team::query()
                            ->where(fn (Builder $query): Builder => $query->whereLike('name', "%{$search}%")->orWhereLike('slug', "%{$search}%"))
                            ->orderBy('name')
                            ->limit(50)
                            ->pluck('name', 'id')
                            ->all())
                        ->getOptionLabelsUsing(fn (array $values): array => Team::query()->whereKey($values)->pluck('name', 'id')->all())
                        ->visible(fn (Get $get): bool => $get('target') === 'teams')
                        ->required(fn (Get $get): bool => $get('target') === 'teams'),
                    Select::make('servers')
                        ->label('Servers')
                        ->multiple()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => $this->serverOptions(Server::query()
                            ->where(fn (Builder $query): Builder => $query->whereLike('name', "%{$search}%")->orWhereLike('ip_address', "%{$search}%"))
                            ->limit(50)))
                        ->getOptionLabelsUsing(fn (array $values): array => $this->serverOptions(Server::query()->whereKey($values)))
                        ->visible(fn (Get $get): bool => $get('target') === 'servers')
                        ->required(fn (Get $get): bool => $get('target') === 'servers'),
                ])
                ->action(fn (array $data, PublishSecurityAdvisoryAction $publish) => $this->publish($data, $publish)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ServerSecurityCheck::query()->where('advisory', $this->advisory)->with('server.team'))
            ->defaultSort('updated_at', 'desc')
            ->poll('10s')
            ->emptyStateHeading('No servers listed yet')
            ->emptyStateDescription('Publish the advisory to list the servers it may affect.')
            ->columns([
                TextColumn::make('server.name')
                    ->label('Server')
                    ->description(fn (ServerSecurityCheck $record): ?string => $record->server->ip_address)
                    ->url(fn (ServerSecurityCheck $record): string => ServerResource::getUrl('view', ['record' => $record->server]))
                    ->searchable(),
                TextColumn::make('server.team.name')
                    ->label('Team')
                    ->url(fn (ServerSecurityCheck $record): ?string => $record->server->team ? TeamResource::getUrl('view', ['record' => $record->server->team]) : null)
                    ->searchable(),
                StatusBadge::column('status'),
                TextColumn::make('detail')->wrap()->placeholder('–'),
                TextColumn::make('created_at')->label('Listed')->dateTime('j M Y')->sortable(),
                TextColumn::make('checked_at')->label('Last checked')->since()->sortable()->placeholder('Never'),
                TextColumn::make('fixed_at')->label('Fixed by Flitops')->dateTime('j M Y, H:i')->placeholder('–'),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(SecurityCheckStatus::cases())->mapWithKeys(fn (SecurityCheckStatus $status): array => [$status->value => $status->label()])),
            ]);
    }

    /**
     * @param  array{target: string, teams?: list<int|string>, servers?: list<int|string>}  $data
     */
    private function publish(array $data, PublishSecurityAdvisoryAction $publish): void
    {
        $teamIds = $data['target'] === 'teams' ? array_map('intval', $data['teams'] ?? []) : null;
        $serverIds = $data['target'] === 'servers' ? array_map('intval', $data['servers'] ?? []) : null;

        $result = $publish->execute($this->securityAdvisory(), $teamIds, $serverIds);

        AdminAuditLog::record(auth()->user(), AdminAction::PublishedSecurityAdvisory, array_filter([
            'advisory' => $this->advisory,
            'target' => $data['target'],
            'chosen teams' => $teamIds,
            'chosen servers' => $serverIds,
            'teams told' => $result['teams'],
            'servers listed' => $result['servers'],
        ], fn (mixed $value): bool => $value !== null));

        $body = $result['servers'] === 0
            ? 'No new servers to list: they\'re already listed or the advisory doesn\'t affect them.'
            : 'Listed '.$result['servers'].' '.Str::plural('server', $result['servers']).' and told '.$result['teams'].' '.Str::plural('team', $result['teams']).'.';

        if ($serverIds !== null && $result['unaffected'] > 0) {
            $body .= ' '.$result['unaffected'].' chosen '.Str::plural('server', $result['unaffected']).' '.($result['unaffected'] === 1 ? 'isn\'t' : 'aren\'t').' affected, so '.($result['unaffected'] === 1 ? 'it was' : 'they were').' skipped.';
        }

        Notification::make()->title('Advisory published')->body($body)->success()->send();
    }

    private function securityAdvisory(): SecurityAdvisory
    {
        return app(SecurityAdvisoryCatalogue::class)->find($this->advisory);
    }

    /**
     * @param  Builder<Server>  $query
     * @return array<int, string>
     */
    private function serverOptions(Builder $query): array
    {
        return $query->with('team')->get()
            ->mapWithKeys(fn (Server $server): array => [$server->id => "{$server->name} · {$server->team?->name} · {$server->ip_address}"])
            ->all();
    }
}
