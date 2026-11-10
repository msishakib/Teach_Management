<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Enums\RoleName;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\FeeType;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamWorkflowService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\GradingScaleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExamModuleTest extends TestCase
{
    use RefreshDatabase;

    protected ExamWorkflowService $workflow;

    protected Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
            GradingScaleSeeder::class,
        ]);

        $this->workflow = app(ExamWorkflowService::class);

        $batch = Batch::factory()->create();
        $this->exam = Exam::factory()->create([
            'batch_id' => $batch->id,
            'academic_session_id' => $batch->session_id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-15',
            'status' => ExamStatus::Draft,
            'fee_amount' => 500,
        ]);
    }

    public function test_valid_status_transitions_follow_workflow(): void
    {
        $this->addSubject();

        $this->assertSame(ExamStatus::Draft, $this->exam->status);

        $this->workflow->schedule($this->exam);
        $this->assertSame(ExamStatus::Scheduled, $this->exam->fresh()->status);

        $this->workflow->start($this->exam->fresh());
        $this->assertSame(ExamStatus::Ongoing, $this->exam->fresh()->status);

        $this->workflow->openMarksEntry($this->exam->fresh());
        $this->assertSame(ExamStatus::MarksEntry, $this->exam->fresh()->status);

        $this->workflow->publish($this->exam->fresh());
        $this->assertSame(ExamStatus::Published, $this->exam->fresh()->status);

        $this->workflow->close($this->exam->fresh());
        $this->assertSame(ExamStatus::Closed, $this->exam->fresh()->status);
    }

    public function test_invalid_status_jump_is_rejected(): void
    {
        $this->addSubject();

        $this->expectException(ValidationException::class);
        $this->workflow->publish($this->exam);
    }

    public function test_cannot_skip_from_draft_to_ongoing(): void
    {
        $this->addSubject();

        $this->expectException(ValidationException::class);
        $this->workflow->start($this->exam);
    }

    public function test_cannot_schedule_without_subjects(): void
    {
        $this->expectException(ValidationException::class);
        $this->workflow->schedule($this->exam);
    }

    public function test_pass_marks_cannot_exceed_full_marks(): void
    {
        $this->expectException(ValidationException::class);

        $this->workflow->addSubject($this->exam, [
            'subject_id' => Subject::factory()->create()->id,
            'exam_date' => '2026-12-05',
            'start_time' => '10:00',
            'duration_minutes' => 90,
            'full_marks' => 100,
            'pass_marks' => 120,
        ]);
    }

    public function test_exam_subject_date_must_fall_inside_exam_range(): void
    {
        $this->expectException(ValidationException::class);

        $this->workflow->addSubject($this->exam, [
            'subject_id' => Subject::factory()->create()->id,
            'exam_date' => '2026-12-20',
            'start_time' => '10:00',
            'duration_minutes' => 90,
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
    }

    public function test_exam_subject_date_before_start_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->workflow->addSubject($this->exam, [
            'subject_id' => Subject::factory()->create()->id,
            'exam_date' => '2026-11-30',
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'full_marks' => 50,
            'pass_marks' => 20,
        ]);
    }

    public function test_exam_fee_generation_is_idempotent(): void
    {
        FeeType::factory()->exam()->create();

        $student = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'batch_id' => $this->exam->batch_id,
            'status' => EnrollmentStatus::Active,
        ]);

        $this->addSubject();
        $this->workflow->schedule($this->exam);

        $first = $this->workflow->generateFees($this->exam->fresh());
        $second = $this->workflow->generateFees($this->exam->fresh());

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $first['skipped']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, StudentInvoice::query()->where('exam_id', $this->exam->id)->count());
        $this->assertSame('exam:'.$this->exam->id, StudentInvoice::query()->first()->month);
    }

    public function test_default_grading_scale_is_seeded(): void
    {
        $this->assertDatabaseHas('grading_scales', [
            'name' => 'Default GPA Scale',
            'is_default' => 1,
        ]);

        $this->assertDatabaseHas('grading_scale_rules', [
            'letter_grade' => 'A+',
            'grade_point' => 5.00,
        ]);

        $this->assertDatabaseHas('grading_scale_rules', [
            'letter_grade' => 'F',
            'grade_point' => 0.00,
        ]);
    }

    public function test_scheduled_exam_can_revert_to_draft(): void
    {
        $this->addSubject();
        $this->workflow->schedule($this->exam);
        $this->workflow->revertToDraft($this->exam->fresh());

        $this->assertSame(ExamStatus::Draft, $this->exam->fresh()->status);
    }

    public function test_exam_permissions_include_publish(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $this->assertTrue($admin->can('exam.view'));
        $this->assertTrue($admin->can('exam.create'));
        $this->assertTrue($admin->can('exam.update'));
        $this->assertTrue($admin->can('exam.publish'));
    }

    protected function addSubject(): ExamSubject
    {
        return $this->workflow->addSubject($this->exam, [
            'subject_id' => Subject::factory()->create()->id,
            'exam_date' => '2026-12-05',
            'start_time' => '10:00',
            'duration_minutes' => 90,
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
    }
}
