<?php

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin resource staff can look at but never change. Getting into the
 * panel at all is the access check ({@see User::canAccessPanel()}), so the
 * customer app's team policies, which ask "is this user in the record's
 * team?", don't apply here.
 */
abstract class ReadOnlyResource extends Resource
{
    protected static bool $shouldSkipAuthorization = true;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
