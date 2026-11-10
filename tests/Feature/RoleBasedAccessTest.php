<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\UserType;
use App\Filament\Resources\TeacherResource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_accountant_cannot_open_teacher_resource(): void
    {
        $accountant = User::factory()->accountant()->create();
        $accountant->assignRole(RoleName::Accountant->value);

        $this->assertFalse($accountant->can('teacher.view'));
        $this->assertFalse($accountant->can('viewAny', \App\Models\Teacher::class));

        $this->actingAs($accountant)
            ->get(TeacherResource::getUrl('index', panel: 'admin'))
            ->assertForbidden();
    }

    public function test_teacher_cannot_open_admin_panel(): void
    {
        $teacher = User::factory()->teacher()->create([
            'type' => UserType::Teacher,
        ]);
        $teacher->assignRole(RoleName::Teacher->value);

        $this->assertFalse($teacher->canAccessPanel(Filament::getPanel('admin')));

        $this->actingAs($teacher)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_super_admin_bypasses_permission_checks(): void
    {
        $superAdmin = User::factory()->admin()->create();
        $superAdmin->assignRole(RoleName::SuperAdmin->value);

        $this->assertTrue($superAdmin->can('teacher.view'));
        $this->assertTrue($superAdmin->can('role.create'));
        $this->assertTrue($superAdmin->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_accountant_can_access_admin_panel_but_not_teacher_module(): void
    {
        $accountant = User::factory()->accountant()->create();
        $accountant->assignRole(RoleName::Accountant->value);

        $this->assertTrue($accountant->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($accountant->can('fee.view'));
        $this->assertFalse($accountant->can('teacher.create'));
    }
}
