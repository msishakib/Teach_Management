<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Unlike Filament's default Authenticate middleware (which aborts 403),
 * users who are logged in but cannot access the current panel are logged
 * out and redirected to that panel's login page. This avoids a hard 403 when
 * switching between admin / teacher / student portals in the same browser.
 */
class EnsureFilamentPanelAccess extends FilamentAuthenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return;
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var Model $user */
        $user = $guard->user();
        $panel = Filament::getCurrentPanel();

        $canAccess = $user instanceof FilamentUser
            ? $user->canAccessPanel($panel)
            : config('app.env') === 'local';

        if ($canAccess) {
            return;
        }

        $guard->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $this->unauthenticated($request, $guards);
    }
}
