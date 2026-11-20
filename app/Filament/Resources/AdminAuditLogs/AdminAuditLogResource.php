<?php

namespace App\Filament\Resources\AdminAuditLogs;

use App\Enums\AdminAction;
use App\Filament\Resources\AdminAuditLogs\Pages\ListAdminAuditLogs;
use App\Filament\Resources\ReadOnlyResource;
use App\Models\AdminAuditLog;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * What Flitops staff have done in the admin. Nobody can change it.
 */
class AdminAuditLogResource extends ReadOnlyResource
{
    protected static ?string $model = AdminAuditLog::class;

    protected static ?string $slug = 'audit-log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $modelLabel = 'audit log entry';

    protected static ?string $pluralModelLabel = 'audit log';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->description('Everything Flitops staff have done in the admin, and who did it. Nobody can edit it.')
            ->emptyStateHeading('Nothing recorded yet')
            ->emptyStateDescription('Actions staff take in the admin, like publishing a security advisory, will appear here.')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('user.email')->label('Who')->placeholder('Deleted user')->searchable(),
                TextColumn::make('action')->label('What')->formatStateUsing(fn (AdminAuditLog $record): string => $record->action->label()),
                TextColumn::make('properties')
                    ->label('Details')
                    ->state(fn (AdminAuditLog $record): string => collect($record->properties)
                        ->map(fn (mixed $value, string $key): string => $key.': '.(is_array($value) ? implode(', ', $value) : (string) $value))
                        ->implode(' · '))
                    ->wrap(),
                TextColumn::make('ip_address')->label('IP')->fontFamily('mono')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->options(collect(AdminAction::cases())->mapWithKeys(fn (AdminAction $action): array => [$action->value => $action->label()])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminAuditLogs::route('/'),
        ];
    }
}
