<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamResultStatus;
use App\Enums\ExamStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamResult;
use App\Models\ExamSubject;
use App\Models\ExamType;
use App\Models\GradingScale;
use App\Models\ResultVersion;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ExamMarksService;
use App\Services\ResultService;
use Database\Seeders\GradingScaleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExamResultsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected ResultService $results;

    protected ExamMarksService $marks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            GradingScaleSeeder::class,
        ]);

        $this->results = app(ResultService::class);
        $this->marks = app(ExamMarksService::class);
    }

    public function test_grade_boundaries_map_to_letter_and_gpa(): void
    {
        [$admin, $exam, $subjects, $students] = $this->makeProcessedReadyExam();

        // Student 0: 85% and 90% => A+ subjects, pass
        $this->marks->saveBulk($admin, $subjects[0], [
            ['student_id' => $students[0]->id, 'marks_obtained' => 85, 'is_absent' => false],
            ['student_id' => $students[1]->id, 'marks_obtained' => 55, 'is_absent' => false],
            ['student_id' => $students[2]->id, 'marks_obtained' => 20, 'is_absent' => false],
        ]);
        $this->marks->saveBulk($admin, $subjects[1], [
            ['student_id' => $students[0]->id, 'marks_obtained' => 90, 'is_absent' => false],
            ['student_id' => $students[1]->id, 'marks_obtained' => 55, 'is_absent' => false],
            ['student_id' => $students[2]->id, 'marks_obtained' => 20, 'is_absent' => false],
        ]);

        $this->results->process($exam, $admin);

        $top = ExamResult::withoutGlobalScopes()
            ->with('items')
            ->where('student_id', $students[0]->id)
            ->firstOrFail();

        $this->assertSame(ExamResultStatus::Pass, $top->status);
        $this->assertSame('A+', $top->items->firstWhere('exam_subject_id', $subjects[0]->id)->letter_grade);
        $this->assertSame('5.00', (string) $top->items->firstWhere('exam_subject_id', $subjects[0]->id)->grade_point);
        $this->assertSame('A+', $top->letter_grade);
        $this->assertSame('5.00', (string) $top->gpa);
    }

    public function test_tie_ranking_assigns_same_rank(): void
    {
        [$admin, $exam, $subjects, $students] = $this->makeProcessedReadyExam();

        foreach ($subjects as $subject) {
            $this->marks->saveBulk($admin, $subject, [
                ['student_id' => $students[0]->id, 'marks_obtained' => 80, 'is_absent' => false],
                ['student_id' => $students[1]->id, 'marks_obtained' => 80, 'is_absent' => false],
                ['student_id' => $students[2]->id, 'marks_obtained' => 60, 'is_absent' => false],
            ]);
        }

        $this->results->process($exam, $admin);

        $ranks = ExamResult::withoutGlobalScopes()
            ->where('exam_id', $exam->id)
            ->orderByDesc('total_marks')
            ->get()
            ->pluck('rank_in_batch', 'student_id');

        $this->assertSame(1, (int) $ranks[$students[0]->id]);
        $this->assertSame(1, (int) $ranks[$students[1]->id]);
        $this->assertSame(3, (int) $ranks[$students[2]->id]);
    }

    public function test_fail_if_any_subject_fails_sets_gpa_zero(): void
    {
        [$admin, $exam, $subjects, $students] = $this->makeProcessedReadyExam();

        $this->marks->saveBulk($admin, $subjects[0], [
            ['student_id' => $students[0]->id, 'marks_obtained' => 90, 'is_absent' => false],
            ['student_id' => $students[1]->id, 'marks_obtained' => 90, 'is_absent' => false],
            ['student_id' => $students[2]->id, 'marks_obtained' => 90, 'is_absent' => false],
        ]);
        $this->marks->saveBulk($admin, $subjects[1], [
            ['student_id' => $students[0]->id, 'marks_obtained' => 20, 'is_absent' => false], // fail
            ['student_id' => $students[1]->id, 'marks_obtained' => 90, 'is_absent' => false],
            ['student_id' => $students[2]->id, 'marks_obtained' => 90, 'is_absent' => false],
        ]);

        $this->results->process($exam, $admin);

        $failed = ExamResult::withoutGlobalScopes()->where('student_id', $students[0]->id)->firstOrFail();
        $this->assertSame(ExamResultStatus::Fail, $failed->status);
        $this->assertSame('0.00', (string) $failed->gpa);
        $this->assertSame('F', $failed->letter_grade);
    }

    public function test_process_blocked_when_marks_incomplete(): void
    {
        [$admin, $exam, $subjects, $students] = $this->makeProcessedReadyExam();

        $this->marks->saveBulk($admin, $subjects[0], [
            ['student_id' => $students[0]->id, 'marks_obtained' => 70, 'is_absent' => false],
            ['student_id' => $students[1]->id, 'marks_obtained' => 70, 'is_absent' => false],
            ['student_id' => $students[2]->id, 'marks_obtained' => 70, 'is_absent' => false],
        ]);
        // subject 1 incomplete

        $this->expectException(ValidationException::class);
        $this->results->process($exam, $admin);
    }

    public function test_publish_and_unpublish_permissions(): void
    {
        [$admin, $exam, $subjects, $students] = $this->makeProcessedReadyExam();

        foreach ($subjects as $subject) {
            $this->marks->saveBulk($admin, $subject, collect($students)->map(fn (Student $s) => [
                'student_id' => $s->id,
                'marks_obtained' => 70,
                'is_absent' => false,
            ])->all());
        }

        $this->results->process($exam, $admin);
        $this->results->publish($exam->fresh(), $admin);

        $this->assertSame(ExamStatus::Published, $exam->fresh()->status);
        $this->assertTrue(ExamResult::withoutGlobalScopes()->where('exam_id', $exam->id)->whereNotNull('published_at')->exists());
        $this->assertSame(1, ResultVersion::query()->where('exam_id', $exam->id)->count());

        $teacher = User::factory()->teacher()->create();
        $teacher->assignRole(RoleName::Teacher->value);

        $this->expectException(ValidationException::class);
        $this->results->unpublish($exam->fresh(), $teacher);
    }

    public function test_unpublished_results_are_hidden_from_students(): void
    {
        [$admin, $exam, $subjects, $students] = $this->makeProcessedReadyExam();

        foreach ($subjects as $subject) {
            $this->marks->saveBulk($admin, $subject, collect($students)->map(fn (Student $s) => [
                'student_id' => $s->id,
                'marks_obtained' => 70,
                'is_absent' => false,
            ])->all());
        }

        $this->results->process($exam, $admin);

        $studentUser = User::factory()->student()->create();
        $studentUser->assignRole(RoleName::Student->value);
        $students[0]->update(['user_id' => $studentUser->id]);

        Auth::login($studentUser);

        $this->assertSame(0, ExamResult::query()->count());

        Auth::logout();
        $this->results->publish($exam->fresh(), $admin);
        Auth::login($studentUser);

        $this->assertSame(1, ExamResult::query()->count());
        $this->assertSame($students[0]->id, ExamResult::query()->first()->student_id);
    }

    public function test_student_cannot_see_another_students_result(): void
    {
        [$admin, $exam, $subjects, $students] = $this->makeProcessedReadyExam();

        foreach ($subjects as $subject) {
            $this->marks->saveBulk($admin, $subject, collect($students)->map(fn (Student $s) => [
                'student_id' => $s->id,
                'marks_obtained' => 70,
                'is_absent' => false,
            ])->all());
        }

        $this->results->process($exam, $admin);
        $this->results->publish($exam->fresh(), $admin);

        $userA = User::factory()->student()->create();
        $userA->assignRole(RoleName::Student->value);
        $students[0]->update(['user_id' => $userA->id]);

        $userB = User::factory()->student()->create();
        $userB->assignRole(RoleName::Student->value);
        $students[1]->update(['user_id' => $userB->id]);

        Auth::login($userA);

        $visible = ExamResult::query()->pluck('student_id')->all();
        $this->assertSame([$students[0]->id], $visible);
        $this->assertFalse(
            ExamResult::query()->where('student_id', $students[1]->id)->exists()
        );

        $other = ExamResult::withoutGlobalScopes()->where('student_id', $students[1]->id)->firstOrFail();
        $this->assertFalse($userA->can('view', $other));
        $this->assertTrue($userA->can('view', ExamResult::query()->first()));
    }

    public function test_public_certificate_verify_shows_limited_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        [$admin, $exam, $subjects, $students] = $this->makeProcessedReadyExam();

        foreach ($subjects as $subject) {
            $this->marks->saveBulk($admin, $subject, collect($students)->map(fn (Student $s) => [
                'student_id' => $s->id,
                'marks_obtained' => 80,
                'is_absent' => false,
            ])->all());
        }

        $this->results->process($exam, $admin);
        $this->results->publish($exam->fresh(), $admin);

        $result = ExamResult::withoutGlobalScopes()->where('student_id', $students[0]->id)->firstOrFail();
        $certificate = app(\App\Services\CertificateService::class)->issueForExamResult($result, $admin);

        $response = $this->get(route('certificates.verify', $certificate->certificate_no));
        $response->assertOk();
        $response->assertSee($students[0]->name);
        $response->assertSee($exam->batch->course->name);
        $response->assertDontSee($students[0]->admission_no);
        $response->assertDontSee((string) $result->gpa);
    }

    /**
     * @return array{0: User, 1: Exam, 2: list<ExamSubject>, 3: list<Student>}
     */
    protected function makeProcessedReadyExam(): array
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $teacher = Teacher::factory()->create();
        $batch = Batch::factory()->create();
        $scale = GradingScale::query()->where('is_default', true)->firstOrFail();

        $exam = Exam::factory()->create([
            'batch_id' => $batch->id,
            'academic_session_id' => $batch->session_id,
            'exam_type_id' => ExamType::factory()->create()->id,
            'grading_scale_id' => $scale->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-15',
            'status' => ExamStatus::MarksEntry,
        ]);

        $subjects = [
            ExamSubject::factory()->create([
                'exam_id' => $exam->id,
                'subject_id' => Subject::factory()->create()->id,
                'teacher_id' => $teacher->id,
                'exam_date' => '2026-12-05',
                'full_marks' => 100,
                'pass_marks' => 33,
            ]),
            ExamSubject::factory()->create([
                'exam_id' => $exam->id,
                'subject_id' => Subject::factory()->create()->id,
                'teacher_id' => $teacher->id,
                'exam_date' => '2026-12-06',
                'full_marks' => 100,
                'pass_marks' => 33,
            ]),
        ];

        $students = Student::factory()->count(3)->create(['status' => StudentStatus::Active])->all();

        foreach ($students as $student) {
            Enrollment::factory()->create([
                'student_id' => $student->id,
                'batch_id' => $batch->id,
                'status' => EnrollmentStatus::Active,
            ]);
        }

        return [$admin, $exam->fresh(['subjects', 'batch.course', 'gradingScale.rules']), $subjects, $students];
    }
}
