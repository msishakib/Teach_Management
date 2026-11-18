<?php

namespace App\Providers;

use App\Support\ActivityLog\AddRequestContext;
use App\Enums\RoleName;
use App\Services\SiteProfileService;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use League\Flysystem\Filesystem;
use Spatie\Activitylog\Traits\LogsActivity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One profile build per request for the public site.
        $this->app->scoped(SiteProfileService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        if (app()->environment('production') && config('security.force_debug_off_in_production', true)) {
            config(['app.debug' => false]);
            ini_set('display_errors', '0');
        }

        Password::defaults(function (): Password {
            $rule = Password::min(max(8, (int) setting(
                'security.password_min_length',
                config('security.password.min_length', 12),
            )));

            if (setting('security.password_mixed_case', config('security.password.mixed_case', true))) {
                $rule->mixedCase();
            }

            if (setting('security.password_numbers', config('security.password.numbers', true))) {
                $rule->numbers();
            }

            if (setting('security.password_symbols', config('security.password.symbols', true))) {
                $rule->symbols();
            }

            if (setting('security.password_uncompromised', config('security.password.uncompromised', false))) {
                $rule->uncompromised();
            }

            return $rule;
        });

        $pipe = new AddRequestContext();

        foreach ([
            \App\Models\Student::class,
            \App\Models\Teacher::class,
            \App\Models\StudentInvoice::class,
            \App\Models\Receipt::class,
            \App\Models\PaymentVoucher::class,
            \App\Models\ReceiveVoucher::class,
            \App\Models\ExamMark::class,
            \App\Models\Setting::class,
        ] as $model) {
            $model::$changesPipes = collect($model::$changesPipes ?? [])
                ->reject(fn ($registered): bool => $registered instanceof AddRequestContext)
                ->push($pipe)
                ->values()
                ->all();
        }

        $this->registerGoogleDriveDisk();

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

        FilamentView::registerRenderHook(
            PanelsRenderHook::SCRIPTS_AFTER,
            fn (): HtmlString => new HtmlString(
                '<script src="'.e(asset('js/filament-sidebar-accordion.js')).'" data-navigate-track></script>'
            ),
        );

        // Visible "Home" button on every panel topbar → public website.
        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): string => view('filament.hooks.website-home-button')->render(),
        );
    }

    /**
     * Register the optional Google Drive filesystem used for off-site backups.
     * The driver is only needed when BACKUP_DISKS contains "google".
     */
    protected function registerGoogleDriveDisk(): void
    {
        if (! class_exists(\Masbug\Flysystem\GoogleDriveAdapter::class)) {
            return;
        }

        Storage::extend('google', function ($app, array $config): FilesystemAdapter {
            $client = new \Google\Client();
            $client->setClientId($config['clientId'] ?? null);
            $client->setClientSecret($config['clientSecret'] ?? null);

            if (! empty($config['refreshToken'])) {
                $client->refreshToken($config['refreshToken']);
            }

            $service = new \Google\Service\Drive($client);

            $options = array_filter([
                'teamDriveId' => $config['teamDriveId'] ?? null,
                'sharedFolderId' => $config['sharedFolderId'] ?? null,
            ], fn ($value): bool => filled($value));

            $adapter = new \Masbug\Flysystem\GoogleDriveAdapter(
                $service,
                $config['folder'] ?? '/',
                $options,
            );

            return new FilesystemAdapter(new Filesystem($adapter), $adapter);
        });
    }
}
