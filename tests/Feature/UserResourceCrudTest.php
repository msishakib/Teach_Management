<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\UserType;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use App\Services\UserService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserResourceCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_create_a_user_with_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $roleId = Role::findByName(RoleName::Accountant->value, 'web')->id;

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Finance User',
                'email' => 'finance@example.com',
                'password' => 'password123',
                'type' => UserType::Admin->value,
                'roles' => [$roleId],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'finance@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(RoleName::Accountant->value));
        $this->assertSame(UserType::Admin, $user->type);
    }

    public function test_admin_can_update_user_roles_and_password_optional(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $user = User::factory()->admin()->create([
            'email' => 'staff@example.com',
        ]);
        $user->assignRole(RoleName::Accountant->value);

        $teacherRoleId = Role::findByName(RoleName::Teacher->value, 'web')->id;

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm([
                'name' => 'Updated Staff',
                'email' => 'staff@example.com',
                'type' => UserType::Teacher->value,
                'password' => null,
                'roles' => [$teacherRoleId],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertSame('Updated Staff', $user->name);
        $this->assertSame(UserType::Teacher, $user->type);
        $this->assertTrue($user->hasRole(RoleName::Teacher->value));
        $this->assertFalse($user->hasRole(RoleName::Accountant->value));
    }

    public function test_user_service_blocks_self_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $this->expectException(ValidationException::class);

        app(UserService::class)->delete($admin, $admin);
    }
}
