<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TwoFactorSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Two-factor authentication';

    protected static ?string $title = 'Two-factor authentication';

    protected static ?int $navigationSort = 92;

    protected static string $view = 'filament.pages.two-factor-settings';

    public bool $enabled = false;

    public ?string $secret = null;

    public string $qrCodeDataUri = '';

    public array $recoveryCodes = [];

    public string $code = '';

    public string $password = '';

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    public function mount(TwoFactorAuthenticationService $twoFactor): void
    {
        abort_unless(static::canAccess(), 403);

        $user = $this->user();

        if (! $user->hasConfirmedTwoFactorAuthentication() && blank($user->decryptedTwoFactorSecret())) {
            $user->forceFill(['two_factor_secret' => $twoFactor->generateSecret()])->save();
        }

        $this->refreshState($twoFactor);
    }

    public function confirm(TwoFactorAuthenticationService $twoFactor): void
    {
        $this->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $user = $this->user();

        if (! $twoFactor->verify($user->decryptedTwoFactorSecret(), $this->code)) {
            $this->addError('code', 'The authentication code is invalid. Please try again.');

            return;
        }

        $codes = $twoFactor->generateRecoveryCodes(
            (int) config('security.two_factor.recovery_codes', 8),
        );

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes,
        ])->save();

        $this->code = '';
        $this->refreshState($twoFactor);

        Notification::make()
            ->title('Two-factor authentication enabled')
            ->body('Store your recovery codes somewhere safe.')
            ->success()
            ->send();
    }

    public function regenerateRecoveryCodes(TwoFactorAuthenticationService $twoFactor): void
    {
        $user = $this->user();

        if (! $user->hasConfirmedTwoFactorAuthentication()) {
            return;
        }

        $codes = $twoFactor->generateRecoveryCodes(
            (int) config('security.two_factor.recovery_codes', 8),
        );

        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();
        $this->recoveryCodes = $codes;

        Notification::make()
            ->title('Recovery codes regenerated')
            ->success()
            ->send();
    }

    public function disable(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $this->user();

        if (! Hash::check($this->password, $user->password)) {
            $this->addError('password', 'The password is incorrect.');

            return;
        }

        $user->clearTwoFactorAuthentication();
        $this->password = '';
        $this->refreshState(app(TwoFactorAuthenticationService::class));

        Notification::make()
            ->title('Two-factor authentication disabled')
            ->success()
            ->send();
    }

    private function refreshState(TwoFactorAuthenticationService $twoFactor): void
    {
        $user = $this->user();

        $this->enabled = $user->hasConfirmedTwoFactorAuthentication();
        $this->secret = $user->decryptedTwoFactorSecret();
        $this->recoveryCodes = $user->recoveryCodes();
        $this->qrCodeDataUri = (! $this->enabled && filled($this->secret))
            ? $twoFactor->qrCodeDataUri($user, $this->secret)
            : '';
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
