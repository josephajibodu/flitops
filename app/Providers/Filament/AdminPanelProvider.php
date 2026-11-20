<?php

namespace App\Providers\Filament;

use App\Http\Middleware\AuthenticateAdmin;
use App\Models\User;
use Filament\Enums\GlobalSearchPosition;
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
    /**
     * Shopify's admin greys, so the panel matches the customer app.
     *
     * @var array<int, string>
     */
    private const array GREY = [
        50 => '#F7F7F7',
        100 => '#F1F1F1',
        200 => '#E3E3E3',
        300 => '#CCCCCC',
        400 => '#B5B5B5',
        500 => '#8A8A8A',
        600 => '#616161',
        700 => '#4A4A4A',
        800 => '#303030',
        900 => '#1A1A1A',
        950 => '#0A0A0A',
    ];

    /**
     * Shopify's buttons and active states are near-black rather than a
     * colour, so "primary" is ink. Filament puts buttons on shade 600.
     *
     * @var array<int, string>
     */
    private const array INK = [
        50 => '#F7F7F7',
        100 => '#F1F1F1',
        200 => '#E3E3E3',
        300 => '#8A8A8A',
        400 => '#4A4A4A',
        500 => '#303030',
        600 => '#303030',
        700 => '#1A1A1A',
        800 => '#1A1A1A',
        900 => '#0A0A0A',
        950 => '#0A0A0A',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): View => view('filament.brand-styles'))
            ->brandName('Flitops Admin')
            ->brandLogo(asset('images/admin/flitops-logo-on-dark.svg'))
            ->brandLogoHeight('1.5rem')
            ->favicon(asset('favicon.svg'))
            ->darkMode(false)
            ->topbar(false)
            ->globalSearch(position: GlobalSearchPosition::Sidebar)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->sidebarWidth('13.75rem')
            ->collapsibleNavigationGroups(false)
            ->colors([
                'primary' => self::INK,
                'gray' => self::GREY,
                'info' => Color::Blue,
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
