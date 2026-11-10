<?php

namespace App\Providers;

use App\Enums\RoleName;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        Gate::before(function ($user, string $ability) {
            if (method_exists($user, 'hasRole') && $user->hasRole(RoleName::SuperAdmin->value)) {
                return true;
            }

            return null;
        });

        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => new HtmlString(
                '<link rel="stylesheet" href="'.e(asset('css/filament-compact.css')).'" data-navigate-track />'
            ),
        );
    }
}
