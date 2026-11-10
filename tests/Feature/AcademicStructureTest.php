<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Filament\Resources\BatchResource\Pages\ListBatches;
use App\Filament\Resources\EnrollmentResource\Pages\CreateEnrollment;
use App\Models\Batch;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_factories_create_related_academic_records(): void
    {
        $routine = ClassRoutine::factory()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assertNotNull($routine->batch->course);
        $this->assertNotNull($routine->batch->session);
        $this->assertNotNull($routine->subject);
        $this->assertNotNull($routine->teacher);
        $this->assertNotNull($enrollment->student);
        $this->assertNotNull($enrollment->batch);
    }

    public function test_student_cannot_have_duplicate_enrollment_in_same_batch(): void
    {
        $student = Student::factory()->create();
        $batch = Batch::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $this->expectException(QueryException::class);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'status' => EnrollmentStatus::Dropped,
        ]);
    }

    public function test_admin_can_list_batches_with_eager_loaded_relations(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        Batch::factory()->count(2)->create();

        Livewire::actingAs($admin)
            ->test(ListBatches::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(Batch::all());
    }

    public function test_enrollment_form_rejects_duplicate_student_batch_pair(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $student = Student::factory()->create();
        $batch = Batch::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
        ]);

        Livewire::actingAs($admin)
            ->test(CreateEnrollment::class)
            ->fillForm([
                'student_id' => $student->id,
                'batch_id' => $batch->id,
                'enrolled_on' => now()->toDateString(),
                'status' => EnrollmentStatus::Active->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['student_id']);
    }
}
