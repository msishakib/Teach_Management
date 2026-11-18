<?php

namespace App\Models\Concerns;

use Illuminate\Contracts\Encryption\DecryptException;

trait HasTwoFactorAuthentication
{
    public function hasConfirmedTwoFactorAuthentication(): bool
    {
        if ($this->two_factor_confirmed_at === null) {
            return false;
        }

        return filled($this->decryptedTwoFactorSecret());
    }

    public function decryptedTwoFactorSecret(): ?string
    {
        try {
            $secret = $this->two_factor_secret;
        } catch (DecryptException) {
            $this->clearTwoFactorAuthentication();

            return null;
        }

        return filled($secret) ? (string) $secret : null;
    }

    /**
     * @return list<string>
     */
    public function recoveryCodes(): array
    {
        try {
            $codes = $this->two_factor_recovery_codes ?? [];
        } catch (DecryptException) {
            $this->clearTwoFactorAuthentication();

            return [];
        }

        return is_array($codes) ? array_values($codes) : [];
    }

    public function clearTwoFactorAuthentication(): void
    {
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }
}
