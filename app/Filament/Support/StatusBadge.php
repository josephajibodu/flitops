<?php

namespace App\Filament\Support;

use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;

/**
 * Status badges for the admin, matching the colours the customer app uses.
 * Works with the app's status enums (their `label()` and `color()`) and with
 * the plain status strings some tables still store.
 */
class StatusBadge
{
    private const COLOR_BY_NAME = [
        'green' => 'success',
        'red' => 'danger',
        'orange' => 'warning',
        'yellow' => 'warning',
        'blue' => 'info',
        'gray' => 'gray',
    ];

    private const COLOR_BY_VALUE = [
        'active' => 'success',
        'installed' => 'success',
        'enabled' => 'success',
        'up' => 'success',
        'failed' => 'danger',
        'error' => 'danger',
        'down' => 'danger',
        'pending' => 'gray',
        'disabled' => 'gray',
        'inactive' => 'gray',
    ];

    public static function column(string $name): TextColumn
    {
        return TextColumn::make($name)
            ->badge()
            ->formatStateUsing(fn (mixed $state): ?string => self::label($state))
            ->color(fn (mixed $state): string => self::color($state));
    }

    public static function entry(string $name): TextEntry
    {
        return TextEntry::make($name)
            ->badge()
            ->formatStateUsing(fn (mixed $state): ?string => self::label($state))
            ->color(fn (mixed $state): string => self::color($state));
    }

    public static function label(mixed $state): ?string
    {
        if ($state instanceof BackedEnum) {
            return method_exists($state, 'label') ? $state->label() : Str::headline((string) $state->value);
        }

        return $state === null ? null : Str::headline((string) $state);
    }

    public static function color(mixed $state): string
    {
        if ($state instanceof BackedEnum && method_exists($state, 'color')) {
            return self::COLOR_BY_NAME[$state->color()] ?? 'gray';
        }

        $value = $state instanceof BackedEnum ? (string) $state->value : (string) $state;

        return self::COLOR_BY_VALUE[$value] ?? 'info';
    }
}
