<?php

namespace App\Providers\Filament;

use App\Http\Middleware\AuthenticateAdmin;
use App\Models\User;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The Flitops staff admin at /admin: read-only views of every team's
 * resources for support, plus publishing security advisories. Staff sign in
 * through the normal Flitops login; {@see User::canAccessPanel()}
 * decides who gets in.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): View => view('filament.brand-styles'))
            ->brandName('Flitops Admin')
            ->brandLogo(asset('images/admin/flitops-logo.svg'))
            ->darkModeBrandLogo(asset('images/admin/flitops-logo-on-dark.svg'))
            ->brandLogoHeight('1.75rem')
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Neutral,
            ])
            ->maxContentWidth(Width::Full)
            ->navigationGroups([
                NavigationGroup::make('Customers'),
                NavigationGroup::make('Infrastructure'),
                NavigationGroup::make('Security'),
                NavigationGroup::make('System'),
            ])
            ->navigationItems([
                NavigationItem::make('Customer app')
                    ->url(fn (): string => url('/dashboard'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->group('System')
                    ->sort(100),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticateAdmin::class,
            ], isPersistent: true);
    }
}
