<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    public function test_admin_login_is_rate_limited_after_configured_attempts(): void
    {
        app(\App\Services\SettingsService::class)->set('security.login_max_attempts', 2);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $user = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('Str0ng!Passw0rd'),
        ]);
        $user->assignRole(RoleName::Admin->value);

        $component = Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
                'remember' => false,
            ]);

        // Two allowed failed attempts, third should be rate limited.
        $component->call('authenticate')->assertHasFormErrors(['email']);
        $component->call('authenticate')->assertHasFormErrors(['email']);
        $component->call('authenticate')->assertNotified();
    }
}
