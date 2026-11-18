<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $minutes = (int) setting(
            'security.session_timeout_minutes',
            config('security.session_timeout_minutes', 60),
        );

        if ($minutes <= 0) {
            return $next($request);
        }

        $lastActivity = (int) $request->session()->get('security.last_activity', 0);

        if ($lastActivity > 0 && (time() - $lastActivity) >= ($minutes * 60)) {
            $loginUrl = Filament::getCurrentPanel()?->getLoginUrl() ?? url('/admin/login');

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to($loginUrl)
                ->with('status', 'Your session expired due to inactivity. Please sign in again.');
        }

        $request->session()->put('security.last_activity', time());

        return $next($request);
    }
}
