<?php

namespace App\Filament\RelationManagers;

use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;

/**
 * A tab on an admin page listing related records that staff can look at but
 * never change. Getting into the panel at all is the access check
 * ({@see User::canAccessPanel()}), so the customer app's team policies don't
 * apply here.
 */
abstract class ReadOnlyRelationManager extends RelationManager
{
    protected static bool $shouldSkipAuthorization = true;

    public function isReadOnly(): bool
    {
        return true;
    }
}
