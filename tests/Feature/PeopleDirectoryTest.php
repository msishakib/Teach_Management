<?php

namespace Tests\Feature;

use App\Enums\GuardianRelation;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Resources\StudentResource\Pages\CreateStudent;
use App\Filament\Resources\StudentResource\Pages\ListStudents;
use App\Filament\Resources\TeacherResource\Pages\CreateTeacher;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\NumberGeneratorService;
use App\Services\StudentService;
use App\Services\TeacherService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PeopleDirectoryTest extends TestCase
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

    public function test_number_generator_increments_yearly_sequence_under_lock(): void
    {
        $generator = app(NumberGeneratorService::class);

        $first = $generator->nextAdmission(2026);
        $second = $generator->nextAdmission(2026);
        $employee = $generator->nextEmployee(2026);

        $this->assertSame('ADM-2026-0001', $first);
        $this->assertSame('ADM-2026-0002', $second);
        $this->assertSame('EMP-2026-0001', $employee);

        $sequences = Setting::query()
            ->where('group', 'numbering')
            ->where('key', 'admission_yearly_sequences')
            ->first()
            ?->value;

        $this->assertSame(2, (int) ($sequences['2026'] ?? 0));
    }

    public function test_student_service_creates_student_guardians_and_linked_user(): void
    {
        $student = app(StudentService::class)->create(
            [
                'name' => 'Ada Student',
                'phone' => '01700000001',
                'status' => StudentStatus::Active,
            ],
            [
                'email' => 'ada.student@example.com',
                'password' => 'password123',
            ],
            [
                [
                    'name' => 'Parent One',
                    'phone' => '01700000002',
                    'relation' => GuardianRelation::Father,
                ],
            ],
        );

        $this->assertMatchesRegularExpression('/^ADM-\d{4}-\d{4}$/', $student->admission_no);
        $this->assertNotNull($student->user_id);
        $this->assertTrue($student->user->hasRole(RoleName::Student->value));
        $this->assertCount(1, $student->guardians);
        $this->assertSame(GuardianRelation::Father->value, $student->guardians->first()->pivot->relation);
    }

    public function test_teacher_service_creates_teacher_with_employee_no_and_linked_user(): void
    {
        $teacher = app(TeacherService::class)->create(
            [
                'name' => 'Tom Teacher',
                'phone' => '01700000003',
                'salary' => '45000.50',
                'joining_date' => '2026-01-15',
            ],
            [
                'email' => 'tom.teacher@example.com',
                'password' => 'password123',
            ],
        );

        $this->assertMatchesRegularExpression('/^EMP-\d{4}-\d{4}$/', $teacher->employee_no);
        $this->assertSame('45000.50', $teacher->salary);
        $this->assertNotNull($teacher->user_id);
        $this->assertTrue($teacher->user->hasRole(RoleName::Teacher->value));
    }

    public function test_concurrent_admission_numbers_remain_unique(): void
    {
        $numbers = [];

        foreach (range(1, 5) as $ignored) {
            DB::transaction(function () use (&$numbers) {
                $numbers[] = app(NumberGeneratorService::class)->nextAdmission(2026);
            });
        }

        $this->assertCount(5, array_unique($numbers));
    }

    public function test_admin_can_create_student_via_filament(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(CreateStudent::class)
            ->fillForm([
                'name' => 'Filament Student',
                'phone' => '01711111111',
                'status' => StudentStatus::Active->value,
                'create_user' => true,
                'user_email' => 'filament.student@example.com',
                'user_password' => 'password123',
                'guardianItems' => [
                    [
                        'name' => 'Guardian A',
                        'relation' => GuardianRelation::Mother->value,
                        'phone' => '01722222222',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $student = Student::query()->where('name', 'Filament Student')->first();

        $this->assertNotNull($student);
        $this->assertNotNull($student->user_id);
        $this->assertCount(1, $student->guardians);
    }

    public function test_admin_can_create_teacher_via_filament(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(CreateTeacher::class)
            ->fillForm([
                'name' => 'Filament Teacher',
                'phone' => '01733333333',
                'salary' => 50000,
                'create_user' => true,
                'user_email' => 'filament.teacher@example.com',
                'user_password' => 'password123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $teacher = Teacher::query()->where('name', 'Filament Teacher')->first();

        $this->assertNotNull($teacher);
        $this->assertNotNull($teacher->employee_no);
        $this->assertNotNull($teacher->user_id);
    }

    public function test_student_list_filters_by_batch_and_status(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $batch = Batch::factory()->create();
        $matching = Student::factory()->create(['status' => StudentStatus::Active, 'name' => 'Match Student']);
        $other = Student::factory()->create(['status' => StudentStatus::Inactive, 'name' => 'Other Student']);

        Enrollment::factory()->create([
            'student_id' => $matching->id,
            'batch_id' => $batch->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ListStudents::class)
            ->filterTable('status', StudentStatus::Active->value)
            ->filterTable('batch_id', $batch->id)
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }
}
