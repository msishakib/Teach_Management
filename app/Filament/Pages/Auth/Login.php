<?php

namespace App\Filament\Pages\Auth;

use Filament\Facades\Filament;
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
}
