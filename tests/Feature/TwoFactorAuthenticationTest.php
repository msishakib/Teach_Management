<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_encrypted_two_factor_secret_is_cleared_instead_of_crashing(): void
    {
        $user = User::factory()->create();

        // Bypass the encrypted cast so the DB holds ciphertext from another APP_KEY.
        DB::table('users')->where('id', $user->id)->update([
            'two_factor_secret' => 'eyJpdiI6ImJhZCIsInZhbHVlIjoiYmFkIiwibWFjIjoiYmFkIiwidGFnIjoiIn0=',
            'two_factor_confirmed_at' => now(),
        ]);

        $fresh = $user->fresh();

        $this->assertFalse($fresh->hasConfirmedTwoFactorAuthentication());
        $this->assertNull($fresh->fresh()->getRawOriginal('two_factor_secret'));
        $this->assertNull($fresh->fresh()->two_factor_confirmed_at);
    }

    public function test_confirmed_two_factor_still_works_with_valid_secret(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['ABCD123456', 'EFGH789012'],
        ])->save();

        $fresh = $user->fresh();

        $this->assertTrue($fresh->hasConfirmedTwoFactorAuthentication());
        $this->assertSame('JBSWY3DPEHPK3PXP', $fresh->decryptedTwoFactorSecret());
        $this->assertSame(['ABCD123456', 'EFGH789012'], $fresh->recoveryCodes());
    }
}
