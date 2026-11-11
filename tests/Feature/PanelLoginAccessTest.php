<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\UserType;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelLoginAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_can_open_student_login_page(): void
    {
        $this->get('/student/login')->assertOk();
        $this->get('/teacher/login')->assertOk();
        $this->get('/admin/login')->assertOk();
    }

    public function test_admin_session_is_cleared_instead_of_403_on_student_panel(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $this->actingAs($admin)
            ->get('/student')
            ->assertRedirect('/student/login');

        $this->assertGuest();
    }

    public function test_admin_visiting_student_login_sees_the_form_not_403(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $this->actingAs($admin)
            ->get('/student/login')
            ->assertOk();

        $this->assertGuest();
    }

    public function test_student_user_can_open_student_dashboard(): void
    {
        $studentUser = User::factory()->create(['type' => UserType::Student]);
        $studentUser->assignRole(RoleName::Student->value);
        Student::factory()->create(['user_id' => $studentUser->id]);

        $this->actingAs($studentUser)
            ->get('/student')
            ->assertOk();
    }
}
