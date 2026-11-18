<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Filament\Pages\TwoFactorSettings;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! setting('security.two_factor_admin_required', config('security.two_factor.enforce_for_admins', false))) {
            return $next($request);
        }

        $isAdmin = $user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
        ]);

        if (! $isAdmin || $user->hasConfirmedTwoFactorAuthentication()) {
            return $next($request);
        }

        if ($request->is('admin/two-factor-settings*') || $request->is('admin/logout')) {
            return $next($request);
        }

        return redirect()->to(TwoFactorSettings::getUrl(panel: 'admin'));
    }
}
