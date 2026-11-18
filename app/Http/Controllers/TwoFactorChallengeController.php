<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class TwoFactorChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUserId($request)) {
            return redirect('/admin/login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $userId = $this->pendingUserId($request);
        $expiresAt = (int) $request->session()->get('two_factor_pending_expires_at', 0);

        if (! $userId || $expiresAt < now()->timestamp) {
            $this->clearPending($request);

            return redirect('/admin/login')
                ->withErrors(['code' => 'Your verification session expired. Please sign in again.']);
        }

        $user = User::query()->find($userId);

        if (! $user) {
            $this->clearPending($request);

            return redirect('/admin/login');
        }

        $rateLimitKey = $this->rateLimitKey($request, $user);

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return back()->withErrors([
                'code' => 'Too many verification attempts. Please wait a minute and try again.',
            ]);
        }

        RateLimiter::hit($rateLimitKey, 60);

        $code = (string) $request->input('code');
        $valid = $twoFactor->verify($user->decryptedTwoFactorSecret(), $code)
            || $twoFactor->verifyRecoveryCode($user, $code);

        if (! $valid) {
            return back()->withErrors(['code' => 'The verification code is invalid.']);
        }

        RateLimiter::clear($rateLimitKey);

        $remember = (bool) $request->session()->get('two_factor_pending_remember', false);

        $this->clearPending($request);
        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended('/admin');
    }

    private function pendingUserId(Request $request): ?int
    {
        $userId = $request->session()->get('two_factor_pending_user_id');
        $expiresAt = (int) $request->session()->get('two_factor_pending_expires_at', 0);

        if (! $userId || $expiresAt < now()->timestamp) {
            return null;
        }

        return (int) $userId;
    }

    private function clearPending(Request $request): void
    {
        $request->session()->forget([
            'two_factor_pending_user_id',
            'two_factor_pending_expires_at',
            'two_factor_pending_remember',
        ]);
    }

    private function rateLimitKey(Request $request, User $user): string
    {
        return 'two-factor-challenge:'.$user->getKey().'|'.$request->ip();
    }
}
