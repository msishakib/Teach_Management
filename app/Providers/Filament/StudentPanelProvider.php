<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Student\Pages\Dashboard;
use App\Http\Middleware\EnsureFilamentPanelAccess;
use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\SetPublicLocale;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class StudentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('student')
            ->path('student')
            ->login(Login::class)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->sidebarWidth('16rem')
            ->discoverResources(in: app_path('Filament/Student/Resources'), for: 'App\\Filament\\Student\\Resources')
            ->discoverPages(in: app_path('Filament/Student/Pages'), for: 'App\\Filament\\Student\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Student/Widgets'), for: 'App\\Filament\\Student\\Widgets')
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
            ]);
    }
}
