<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Models\User;
use App\Services\PermissionCatalogService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleResourceCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_create_a_role_with_permissions(): void
    {
        $user = User::factory()->admin()->create();
        $user->assignRole(RoleName::SuperAdmin->value);

        Livewire::actingAs($user)
            ->test(CreateRole::class)
            ->fillForm([
                'name' => 'registrar',
                'guard_name' => 'web',
                'module_permissions' => [
                    'student' => ['student.view', 'student.create'],
                    'teacher' => ['teacher.view'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::findByName('registrar', 'web');

        $this->assertTrue($role->hasPermissionTo('student.view'));
        $this->assertTrue($role->hasPermissionTo('student.create'));
        $this->assertTrue($role->hasPermissionTo('teacher.view'));
        $this->assertFalse($role->hasPermissionTo('teacher.create'));
    }

    public function test_super_admin_can_update_a_role_permissions(): void
    {
        $user = User::factory()->admin()->create();
        $user->assignRole(RoleName::SuperAdmin->value);

        $role = Role::findByName(RoleName::Accountant->value, 'web');
        $emptyModules = collect(app(PermissionCatalogService::class)->groupedPermissionOptions())
            ->mapWithKeys(fn (array $options, string $module) => [$module => []])
            ->all();

        Livewire::actingAs($user)
            ->test(EditRole::class, ['record' => $role->getKey()])
            ->fillForm([
                'name' => RoleName::Accountant->value,
                'guard_name' => 'web',
                'module_permissions' => array_merge($emptyModules, [
                    'fee' => ['fee.view', 'fee.create'],
                    'teacher' => ['teacher.view'],
                ]),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $role->refresh();

        $this->assertTrue($role->hasPermissionTo('fee.view'));
        $this->assertTrue($role->hasPermissionTo('fee.create'));
        $this->assertTrue($role->hasPermissionTo('teacher.view'));
        $this->assertFalse($role->hasPermissionTo('payment.view'));
        $this->assertCount(3, $role->permissions);
    }
}
