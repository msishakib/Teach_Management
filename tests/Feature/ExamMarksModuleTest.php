<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Jobs\ImportExamMarksCsvJob;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamMarkLog;
use App\Models\ExamSubject;
use App\Models\ExamType;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ExamMarksService;
use App\Services\ExamWorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExamMarksModuleTest extends TestCase
{
    use RefreshDatabase;

    protected ExamMarksService $marks;

    protected ExamWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
        ]);

        $this->marks = app(ExamMarksService::class);
        $this->workflow = app(ExamWorkflowService::class);
    }

    public function test_bulk_save_upserts_in_one_statement_and_validates_bounds(): void
    {
        [$admin, $exam, $subject, $students] = $this->makeMarksEntryContext();

        $rows = [
            ['student_id' => $students[0]->id, 'marks_obtained' => 85, 'is_absent' => false],
            ['student_id' => $students[1]->id, 'marks_obtained' => null, 'is_absent' => true],
            ['student_id' => $students[2]->id, 'marks_obtained' => 40, 'is_absent' => false],
        ];

        DB::enableQueryLog();

        $result = $this->marks->saveBulk($admin, $subject, $rows);

        $queries = collect(DB::getQueryLog());
        $upserts = $queries->filter(fn (array $query) => str_contains(strtolower($query['query']), 'exam_marks')
            && (str_contains(strtolower($query['query']), 'upsert')
                || str_contains(strtolower($query['query']), 'on conflict')
                || str_contains(strtolower($query['query']), 'on duplicate')));

        $this->assertGreaterThanOrEqual(1, $upserts->count());
        $this->assertSame(3, $result['saved']);
        $this->assertSame(3, ExamMark::query()->count());
        $this->assertNull(ExamMark::query()->where('student_id', $students[1]->id)->value('marks_obtained'));
        $this->assertTrue((bool) ExamMark::query()->where('student_id', $students[1]->id)->value('is_absent'));
    }

    public function test_marks_cannot_exceed_full_marks_or_be_negative(): void
    {
        [$admin, $exam, $subject, $students] = $this->makeMarksEntryContext();

        try {
            $this->marks->saveBulk($admin, $subject, [
                ['student_id' => $students[0]->id, 'marks_obtained' => 150, 'is_absent' => false],
            ]);
            $this->fail('Expected ValidationException for marks above full_marks');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->expectException(ValidationException::class);
        $this->marks->saveBulk($admin, $subject, [
            ['student_id' => $students[0]->id, 'marks_obtained' => -1, 'is_absent' => false],
        ]);
    }

    public function test_absent_students_must_have_null_marks(): void
    {
        [$admin, $exam, $subject, $students] = $this->makeMarksEntryContext();

        $this->expectException(ValidationException::class);
        $this->marks->saveBulk($admin, $subject, [
            ['student_id' => $students[0]->id, 'marks_obtained' => 10, 'is_absent' => true],
        ]);
    }

    public function test_teacher_can_only_enter_assigned_subject_marks(): void
    {
        [$admin, $exam, $assignedSubject, $students, $otherSubject, $teacherUser] = $this->makeMarksEntryContext(withOtherSubject: true);

        $this->marks->saveBulk($teacherUser, $assignedSubject, [
            ['student_id' => $students[0]->id, 'marks_obtained' => 70, 'is_absent' => false],
        ]);

        $this->assertSame(1, ExamMark::query()->where('exam_subject_id', $assignedSubject->id)->count());

        $this->expectException(ValidationException::class);
        $this->marks->saveBulk($teacherUser, $otherSubject, [
            ['student_id' => $students[0]->id, 'marks_obtained' => 70, 'is_absent' => false],
        ]);
    }

    public function test_teacher_cannot_enter_marks_outside_marks_entry_status(): void
    {
        [$admin, $exam, $subject, $students, , $teacherUser] = $this->makeMarksEntryContext();

        $exam->update(['status' => ExamStatus::Ongoing]);

        $this->expectException(ValidationException::class);
        $this->marks->saveBulk($teacherUser, $subject->fresh(), [
            ['student_id' => $students[0]->id, 'marks_obtained' => 50, 'is_absent' => false],
        ]);
    }

    public function test_published_marks_require_override_permission_and_reason_and_write_audit_log(): void
    {
        [$admin, $exam, $subject, $students, , $teacherUser] = $this->makeMarksEntryContext();

        $this->marks->saveBulk($admin, $subject, [
            ['student_id' => $students[0]->id, 'marks_obtained' => 60, 'is_absent' => false],
        ]);

        $exam->update(['status' => ExamStatus::Published]);
        $subject = $subject->fresh(['exam']);

        try {
            $this->marks->saveBulk($teacherUser, $subject, [
                ['student_id' => $students[0]->id, 'marks_obtained' => 65, 'is_absent' => false],
            ], 'typo fix');
            $this->fail('Expected ValidationException for teacher without override');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        try {
            $this->marks->saveBulk($admin, $subject, [
                ['student_id' => $students[0]->id, 'marks_obtained' => 65, 'is_absent' => false],
            ]);
            $this->fail('Expected ValidationException for missing reason');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        $this->marks->saveBulk($admin, $subject, [
            ['student_id' => $students[0]->id, 'marks_obtained' => 65, 'is_absent' => false],
        ], 'Corrected transcription error');

        $this->assertSame('65.00', (string) ExamMark::query()->where('student_id', $students[0]->id)->value('marks_obtained'));
        $this->assertDatabaseHas('exam_mark_logs', [
            'old_marks' => 60.00,
            'new_marks' => 65.00,
            'reason' => 'Corrected transcription error',
            'changed_by' => $admin->id,
        ]);
        $this->assertSame(1, ExamMarkLog::query()->count());
    }

    public function test_marks_entry_changes_also_write_audit_logs(): void
    {
        [$admin, $exam, $subject, $students] = $this->makeMarksEntryContext();

        $this->marks->saveBulk($admin, $subject, [
            ['student_id' => $students[0]->id, 'marks_obtained' => 55, 'is_absent' => false],
        ]);

        $this->marks->saveBulk($admin, $subject, [
            ['student_id' => $students[0]->id, 'marks_obtained' => 58, 'is_absent' => false],
        ]);

        $this->assertSame(1, ExamMarkLog::query()->count());
        $this->assertDatabaseHas('exam_mark_logs', [
            'old_marks' => 55.00,
            'new_marks' => 58.00,
        ]);
    }

    public function test_progress_counts_complete_subjects(): void
    {
        [$admin, $exam, $subject, $students] = $this->makeMarksEntryContext();

        $progress = $this->marks->progress($exam);
        $this->assertSame(1, $progress['subjects_total']);
        $this->assertSame(0, $progress['subjects_complete']);

        $rows = collect($students)->map(fn (Student $student) => [
            'student_id' => $student->id,
            'marks_obtained' => 50,
            'is_absent' => false,
        ])->all();

        $this->marks->saveBulk($admin, $subject, $rows);

        $progress = $this->marks->progress($exam->fresh());
        $this->assertSame(1, $progress['subjects_complete']);
        $this->assertSame(100.0, $progress['percent']);
    }

    public function test_csv_import_job_validates_row_by_row_and_writes_error_report(): void
    {
        Notification::fake();
        Storage::fake('local');

        [$admin, $exam, $subject, $students] = $this->makeMarksEntryContext();

        $path = 'exam-marks-imports/test.csv';
        $csv = implode("\n", [
            'admission_no,student_name,marks_obtained,is_absent,remarks',
            "{$students[0]->admission_no},{$students[0]->name},80,0,",
            'MISSING-NO,Ghost,10,0,',
            "{$students[1]->admission_no},{$students[1]->name},999,0,",
            "{$students[2]->admission_no},{$students[2]->name},,1,",
        ]);
        Storage::disk('local')->put($path, $csv);

        (new ImportExamMarksCsvJob(
            examSubjectId: $subject->id,
            actorId: $admin->id,
            disk: 'local',
            path: $path,
        ))->handle($this->marks);

        $this->assertSame(2, ExamMark::query()->count());
        $this->assertTrue(ExamMark::query()->where('student_id', $students[2]->id)->value('is_absent'));

        Notification::assertSentTo($admin, \App\Notifications\ExamMarksImportFinished::class, function ($notification) {
            return $notification->imported === 2
                && $notification->failed === 2
                && filled($notification->errorReportPath);
        });
    }

    public function test_admin_has_exam_override_permission(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $this->assertTrue($admin->can('exam.override'));
    }

    /**
     * @return array{0: User, 1: Exam, 2: ExamSubject, 3: list<Student>, 4?: ExamSubject, 5?: User}
     */
    protected function makeMarksEntryContext(bool $withOtherSubject = false): array
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $teacherUser = User::factory()->teacher()->create();
        $teacherUser->assignRole(RoleName::Teacher->value);
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
        $otherTeacher = Teacher::factory()->create();

        $batch = Batch::factory()->create();
        $exam = Exam::factory()->create([
            'batch_id' => $batch->id,
            'academic_session_id' => $batch->session_id,
            'exam_type_id' => ExamType::factory()->create()->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-15',
            'status' => ExamStatus::MarksEntry,
        ]);

        $subject = ExamSubject::factory()->create([
            'exam_id' => $exam->id,
            'subject_id' => Subject::factory()->create()->id,
            'teacher_id' => $teacher->id,
            'exam_date' => '2026-12-05',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);

        $students = Student::factory()->count(3)->create(['status' => StudentStatus::Active]);
        foreach ($students as $student) {
            Enrollment::factory()->create([
                'student_id' => $student->id,
                'batch_id' => $batch->id,
                'status' => EnrollmentStatus::Active,
            ]);
        }

        $otherSubject = null;

        if ($withOtherSubject) {
            $otherSubject = ExamSubject::factory()->create([
                'exam_id' => $exam->id,
                'subject_id' => Subject::factory()->create()->id,
                'teacher_id' => $otherTeacher->id,
                'exam_date' => '2026-12-06',
                'full_marks' => 100,
                'pass_marks' => 33,
            ]);
        }

        return [$admin, $exam, $subject, $students->all(), $otherSubject, $teacherUser];
    }
}
