<?php

namespace App\Http\Middleware;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;

/**
 * Filament's sign-in check for the admin panel, which also tells staff
 * without two-factor sign-in why they're refused instead of a bare
 * "Forbidden". Who gets in is decided by {@see User::canAccessPanel()}.
 */
class AuthenticateAdmin extends Authenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();

        abort_if(
            $user instanceof User && $user->is_admin && $user->needsTwoFactorForAdmin(),
            403,
            'Turn on two-factor sign-in in your security settings to use the admin panel.',
        );

        parent::authenticate($request, $guards);
    }
}
