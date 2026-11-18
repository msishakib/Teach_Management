<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\Login as BaseLogin;

/**
 * If the browser already has a session for a different portal (e.g. admin),
 * clear it so the correct panel login form can be shown instead of a 403.
 */
class Login extends BaseLogin
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $user = Filament::auth()->user();
            $panel = Filament::getCurrentPanel();

            if (
                $user instanceof FilamentUser
                && $panel
                && ! $user->canAccessPanel($panel)
            ) {
                Filament::auth()->logout();

                session()->invalidate();
                session()->regenerateToken();
            } else {
                redirect()->intended(Filament::getUrl());

                return;
            }
        }

        $this->form->fill();
    }

    public function authenticate(): ?LoginResponse
    {
        $maxAttempts = max(1, (int) setting(
            'security.login_max_attempts',
            config('security.login.max_attempts', 5),
        ));

        try {
            $this->rateLimit($maxAttempts);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();

            $this->throwFailureValidationException();
        }

        if ($user instanceof User && $user->hasConfirmedTwoFactorAuthentication()) {
            Filament::auth()->logout();

            session()->put('two_factor_pending_user_id', $user->getKey());
            session()->put('two_factor_pending_expires_at', now()->addMinutes(5)->timestamp);
            session()->put('two_factor_pending_remember', (bool) ($data['remember'] ?? false));
            session()->put('url.intended', Filament::getUrl());
            session()->regenerate();

            $this->redirect(route('two-factor.challenge'));

            return null;
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }
}
