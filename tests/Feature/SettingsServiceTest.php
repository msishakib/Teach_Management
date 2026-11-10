<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
        ]);
    }

    public function test_settings_are_cached_forever_and_cleared_on_update(): void
    {
        $service = app(SettingsService::class);
        $cacheKey = $service->cacheKey();

        Cache::forget($cacheKey);

        $this->assertNull(Cache::get($cacheKey));

        $this->assertSame('Teach Management Institute', $service->get('general.institute_name'));
        $this->assertTrue(Cache::has($cacheKey));

        $service->set('general.institute_name', 'Updated Institute');

        $this->assertFalse(Cache::has($cacheKey));
        $this->assertSame('Updated Institute', setting('general.institute_name'));
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_setting_helper_returns_default_when_missing(): void
    {
        Setting::query()->where('group', 'general')->where('key', 'institute_name')->delete();
        app(SettingsService::class)->forgetCache();

        $this->assertSame('Fallback School', setting('general.institute_name', 'Fallback School'));
    }

    public function test_seeded_defaults_cover_all_groups(): void
    {
        $this->assertNotNull(setting('general.currency_symbol'));
        $this->assertIsArray(setting('academic.month_names'));
        $this->assertCount(12, setting('academic.month_names'));
        $this->assertSame(7, setting('finance.fiscal_year_start_month'));
        $this->assertFalse(setting('notification.sms_enabled'));
        $this->assertTrue(setting('notification.email_enabled'));
        $this->assertSame('ADM', setting('numbering.admission_prefix'));
        $this->assertSame('EMP', setting('numbering.employee_prefix'));
        $this->assertSame(4, setting('numbering.padding'));
        $this->assertSame(75, setting('attendance.min_percent'));
        $this->assertFalse(setting('attendance.notify_guardian_on_absent'));
    }

    public function test_admin_can_open_settings_page_and_accountant_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $accountant = User::factory()->accountant()->create();
        $accountant->assignRole(RoleName::Accountant->value);

        $this->assertTrue($admin->can('settings.update'));
        $this->assertFalse($accountant->can('settings.update'));

        Livewire::actingAs($admin)
            ->test(ManageSettings::class)
            ->assertSuccessful();

        Livewire::actingAs($accountant)
            ->test(ManageSettings::class)
            ->assertForbidden();
    }

    public function test_settings_page_persists_updates(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(ManageSettings::class)
            ->fillForm([
                'general.institute_name' => 'Campus One',
                'general.currency_symbol' => '$',
                'general.date_format' => 'Y-m-d',
                'general.timezone' => 'UTC',
                'notification.sms_enabled' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Campus One', setting('general.institute_name'));
        $this->assertSame('$', setting('general.currency_symbol'));
        $this->assertTrue(setting('notification.sms_enabled'));
    }
}
