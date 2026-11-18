<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\EnsureFilamentPanelAccess;
use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Http\Middleware\SetPublicLocale;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->sidebarWidth('16rem')
            ->navigationGroups([
                NavigationGroup::make('People')->collapsed(),
                NavigationGroup::make('Academics')->collapsed(),
                NavigationGroup::make('Attendance')->collapsed(),
                NavigationGroup::make('Finance')->collapsed(),
                NavigationGroup::make('Access Control')->collapsed(),
                NavigationGroup::make('System')->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->userMenuItems([
                'home' => MenuItem::make()
                    ->label('Website home')
                    ->icon('heroicon-o-home')
                    ->url(fn (): string => route('home', [
                        'locale' => SetPublicLocale::preferred(request()),
                    ]))
                    ->sort(-1),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                EnforceSessionTimeout::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                EnsureFilamentPanelAccess::class,
                RequireTwoFactorAuthentication::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ]);
    }
}
