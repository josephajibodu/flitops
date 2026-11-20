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
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
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
            ->brandName('Flitops Admin')
            ->brandLogo(asset('images/admin/flitops-logo-on-dark.svg'))
            ->brandLogoHeight('1.5rem')
            ->favicon(asset('favicon.svg'))
            ->font('Inter')
            ->colors([
                'primary' => $this->inkPalette(),
                'gray' => Color::Neutral,
                'success' => Color::Emerald,
            ])
            ->breadcrumbs(false)
            ->assets([
                Css::make('admin-theme', resource_path('css/filament/admin/theme.css')),
            ])
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('15rem')
            ->maxContentWidth(Width::Full)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
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

    /**
     * A near-black palette so primary actions read as Shopify-style dark
     * buttons, as in the IHADHL admin.
     *
     * @return array<int, string>
     */
    private function inkPalette(): array
    {
        return [
            50 => '#f7f7f7',
            100 => '#ebebeb',
            200 => '#e3e3e3',
            300 => '#cccccc',
            400 => '#8a8a8a',
            500 => '#616161',
            600 => '#303030',
            700 => '#1a1a1a',
            800 => '#141414',
            900 => '#0f0f0f',
            950 => '#0a0a0a',
        ];
    }
}
