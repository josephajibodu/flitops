<?php

namespace App\Filament\Pages;

use App\Enums\SecurityCheckStatus;
use App\Models\ServerSecurityCheck;
use App\Services\SecurityAdvisories\SecurityAdvisory;
use App\Services\SecurityAdvisories\SecurityAdvisoryCatalogue;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Every security advisory Flitops has written, and how far customers have got
 * with each. Advisories live in code ({@see SecurityAdvisoryCatalogue}); each
 * one's page lists its servers and is where staff publish it.
 */
class SecurityAdvisories extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    protected static ?string $title = 'Security advisories';

    protected static ?int $navigationSort = 1;

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->advisoryRows())
            ->description('Publishing an advisory lists the affected servers on each team\'s Security updates page and emails the people who manage them. Customers then check each server and choose to let Flitops install the fix or follow the guide. Nothing runs on a server from here.')
            ->columns([
                TextColumn::make('title')->description(fn (array $record): string => $record['key'])->wrap(),
                TextColumn::make('severity')->badge()->color(fn (string $state): string => $state === 'critical' ? 'danger' : 'warning'),
                TextColumn::make('published_on')->label('Written'),
                TextColumn::make('listed')->label('Servers listed')->alignEnd(),
                TextColumn::make('needs_attention')->label('Still need action')->alignEnd()->color(fn (int $state): ?string => $state > 0 ? 'warning' : null),
                TextColumn::make('protected')->label('Protected')->alignEnd(),
            ])
            ->recordUrl(fn (array $record): string => SecurityAdvisoryServers::getUrl(['advisory' => $record['key']]));
    }

    /**
     * @return array<string, array{key: string, title: string, severity: string, published_on: string, listed: int, needs_attention: int, protected: int}>
     */
    private function advisoryRows(): array
    {
        $counts = ServerSecurityCheck::query()
            ->toBase()
            ->selectRaw('advisory, status, count(*) as total')
            ->groupBy('advisory', 'status')
            ->get()
            ->groupBy('advisory');

        return collect(app(SecurityAdvisoryCatalogue::class)->all())
            ->mapWithKeys(function (SecurityAdvisory $advisory) use ($counts): array {
                $byStatus = ($counts[$advisory->key()] ?? collect())->pluck('total', 'status')->map(fn (mixed $total): int => (int) $total);

                return [$advisory->key() => [
                    'key' => $advisory->key(),
                    'title' => $advisory->title(),
                    'severity' => $advisory->severity(),
                    'published_on' => $advisory->publishedOn(),
                    'listed' => $byStatus->sum(),
                    'needs_attention' => $byStatus->except([SecurityCheckStatus::Covered->value])->sum(),
                    'protected' => $byStatus->get(SecurityCheckStatus::Covered->value, 0),
                ]];
            })
            ->all();
    }
}
