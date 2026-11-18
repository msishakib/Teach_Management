<?php

namespace App\Services;

use App\Models\User;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Str;

class TwoFactorAuthenticationService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $secret;
    }

    public function generateRecoveryCodes(int $count = 8): array
    {
        $count = max(1, $count);

        return collect(range(1, $count))
            ->map(fn (): string => Str::upper(Str::random(10)))
            ->all();
    }

    public function verify(?string $secret, ?string $code): bool
    {
        if (blank($secret) || blank($code)) {
            return false;
        }

        $code = preg_replace('/\D/', '', (string) $code);

        if (strlen((string) $code) !== 6) {
            return false;
        }

        $window = max(0, (int) config('security.two_factor.window', 1));
        $counter = (int) floor(time() / 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->code($secret, $counter + $offset), (string) $code)) {
                return true;
            }
        }

        return false;
    }

    public function code(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $time = pack('N*', 0).pack('N*', $counter);

        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        $issuer = (string) setting('general.institute_name', config('app.name', 'Teach Management'));
        $label = rawurlencode($issuer.':'.$user->email);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ]);

        return "otpauth://totp/{$label}?{$params}";
    }

    public function qrCodeDataUri(User $user, string $secret): string
    {
        $result = (new PngWriter)->write(
            QrCode::create($this->otpauthUrl($user, $secret))
                ->setSize(220)
                ->setMargin(10),
        );

        return $result->getDataUri();
    }

    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->recoveryCodes();
        $normalized = Str::upper(trim($code));

        if (! in_array($normalized, $codes, true)) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => array_values(array_diff($codes, [$normalized])),
        ])->save();

        return true;
    }

    private function base32Decode(string $secret): string
    {
        $secret = Str::upper(preg_replace('/[^A-Z2-7]/', '', $secret));
        $bits = '';

        foreach (str_split($secret) as $char) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }
}
