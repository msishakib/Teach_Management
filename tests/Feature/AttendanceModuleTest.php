<?php

namespace Tests\Feature;

use App\Enums\AttendanceSessionStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\RoleName;
use App\Enums\StudentAttendanceStatus;
use App\Enums\StudentStatus;
use App\Models\AttendanceSession;
use App\Models\Batch;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\LeaveRequest;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AttendanceReportService;
use App\Services\AttendanceService;
use App\Services\LeaveRequestService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendanceModuleTest extends TestCase
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

    public function test_bulk_save_upserts_all_enrolled_students_in_one_statement(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $teacher = Teacher::factory()->create(['user_id' => $admin->id]);
        $batch = Batch::factory()->create();
        ClassRoutine::factory()->create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
        ]);

        $students = Student::factory()->count(3)->create(['status' => StudentStatus::Active]);
        foreach ($students as $student) {
            Enrollment::factory()->create([
                'student_id' => $student->id,
                'batch_id' => $batch->id,
                'status' => EnrollmentStatus::Active,
            ]);
        }

        $inactive = Student::factory()->create(['status' => StudentStatus::Inactive]);
        Enrollment::factory()->create([
            'student_id' => $inactive->id,
            'batch_id' => $batch->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $rows = $students->map(fn (Student $student, int $index) => [
            'student_id' => $student->id,
            'status' => $index === 0
                ? StudentAttendanceStatus::Absent->value
                : StudentAttendanceStatus::Present->value,
        ])->all();

        // Include inactive student id — service must ignore it.
        $rows[] = [
            'student_id' => $inactive->id,
            'status' => StudentAttendanceStatus::Present->value,
        ];

        DB::enableQueryLog();

        $session = app(AttendanceService::class)->saveBulk(
            actor: $admin,
            batch: $batch,
            date: now(),
            rows: $rows,
            teacherId: $teacher->id,
        );

        $queries = collect(DB::getQueryLog());
        $upserts = $queries->filter(fn (array $query) => str_contains(strtolower($query['query']), 'student_attendances')
            && (str_contains(strtolower($query['query']), 'upsert')
                || str_contains(strtolower($query['query']), 'on conflict')
                || str_contains(strtolower($query['query']), 'on duplicate')));

        $this->assertTrue($upserts->isNotEmpty(), 'Expected a single bulk upsert for student_attendances.');
        $this->assertSame(3, StudentAttendance::query()->where('attendance_session_id', $session->id)->count());
        $this->assertDatabaseMissing('student_attendances', [
            'attendance_session_id' => $session->id,
            'student_id' => $inactive->id,
        ]);

        // Second save updates via upsert (no duplicate rows).
        $rows[0]['status'] = StudentAttendanceStatus::Late->value;
        app(AttendanceService::class)->saveBulk(
            actor: $admin,
            batch: $batch,
            date: now(),
            rows: $rows,
            teacherId: $teacher->id,
        );

        $this->assertSame(3, StudentAttendance::query()->where('attendance_session_id', $session->id)->count());
        $this->assertSame(
            StudentAttendanceStatus::Late,
            StudentAttendance::query()
                ->where('attendance_session_id', $session->id)
                ->where('student_id', $students[0]->id)
                ->first()
                ->status,
        );
    }

    public function test_unique_constraints_prevent_duplicate_session_and_student_rows(): void
    {
        $teacher = Teacher::factory()->create();
        $admin = User::factory()->admin()->create();
        $batch = Batch::factory()->create();
        $subject = Subject::factory()->create();
        $student = Student::factory()->create();

        AttendanceSession::factory()->create([
            'batch_id' => $batch->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'taken_by' => $admin->id,
            'date' => now()->toDateString(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        AttendanceSession::factory()->create([
            'batch_id' => $batch->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'taken_by' => $admin->id,
            'date' => now()->toDateString(),
        ]);
    }

    public function test_unique_constraint_prevents_duplicate_student_attendance(): void
    {
        $session = AttendanceSession::factory()->create();
        $student = Student::factory()->create();

        StudentAttendance::factory()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        StudentAttendance::factory()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_teacher_can_only_take_attendance_for_assigned_batches(): void
    {
        $teacherUser = User::factory()->teacher()->create();
        $teacherUser->assignRole(RoleName::Teacher->value);
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);

        $assignedBatch = Batch::factory()->create();
        $otherBatch = Batch::factory()->create();

        ClassRoutine::factory()->create([
            'batch_id' => $assignedBatch->id,
            'teacher_id' => $teacher->id,
        ]);

        $student = Student::factory()->create(['status' => StudentStatus::Active]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'batch_id' => $assignedBatch->id,
            'status' => EnrollmentStatus::Active,
        ]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'batch_id' => $otherBatch->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $service = app(AttendanceService::class);

        $this->assertTrue($service->teacherCanAccessBatch($teacherUser, $assignedBatch));
        $this->assertFalse($service->teacherCanAccessBatch($teacherUser, $otherBatch));

        $session = $service->saveBulk(
            actor: $teacherUser,
            batch: $assignedBatch,
            date: now(),
            rows: [['student_id' => $student->id, 'status' => StudentAttendanceStatus::Present->value]],
            teacherId: $teacher->id,
        );

        $this->assertNotNull($session->id);

        $this->expectException(ValidationException::class);

        $service->saveBulk(
            actor: $teacherUser,
            batch: $otherBatch,
            date: now(),
            rows: [['student_id' => $student->id, 'status' => StudentAttendanceStatus::Present->value]],
            teacherId: $teacher->id,
        );
    }

    public function test_future_dates_and_finalized_sessions_are_blocked(): void
    {
        $teacherUser = User::factory()->teacher()->create();
        $teacherUser->assignRole(RoleName::Teacher->value);
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
        $batch = Batch::factory()->create();
        ClassRoutine::factory()->create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
        ]);
        $student = Student::factory()->create(['status' => StudentStatus::Active]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $service = app(AttendanceService::class);

        try {
            $service->saveBulk(
                actor: $teacherUser,
                batch: $batch,
                date: now()->addDay(),
                rows: [['student_id' => $student->id, 'status' => StudentAttendanceStatus::Present->value]],
                teacherId: $teacher->id,
            );
            $this->fail('Expected future date to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('date', $exception->errors());
        }

        $session = $service->saveBulk(
            actor: $teacherUser,
            batch: $batch,
            date: now(),
            rows: [['student_id' => $student->id, 'status' => StudentAttendanceStatus::Present->value]],
            teacherId: $teacher->id,
            finalize: true,
        );

        $this->assertSame(AttendanceSessionStatus::Finalized, $session->status);
        $this->assertFalse($teacherUser->can('attendance.override'));

        $this->expectException(ValidationException::class);

        $service->saveBulk(
            actor: $teacherUser,
            batch: $batch,
            date: now(),
            rows: [['student_id' => $student->id, 'status' => StudentAttendanceStatus::Absent->value]],
            teacherId: $teacher->id,
        );
    }

    public function test_admin_with_override_can_edit_finalized_session(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $teacher = Teacher::factory()->create();
        $batch = Batch::factory()->create();
        $student = Student::factory()->create(['status' => StudentStatus::Active]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $service = app(AttendanceService::class);

        $service->saveBulk(
            actor: $admin,
            batch: $batch,
            date: now(),
            rows: [['student_id' => $student->id, 'status' => StudentAttendanceStatus::Present->value]],
            teacherId: $teacher->id,
            finalize: true,
        );

        $this->assertTrue($admin->can('attendance.override'));

        $service->saveBulk(
            actor: $admin,
            batch: $batch,
            date: now(),
            rows: [['student_id' => $student->id, 'status' => StudentAttendanceStatus::Absent->value]],
            teacherId: $teacher->id,
        );

        $this->assertSame(
            StudentAttendanceStatus::Absent,
            StudentAttendance::query()->where('student_id', $student->id)->first()->status,
        );
    }

    public function test_approved_leave_auto_marks_excused(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $teacher = Teacher::factory()->create();
        $batch = Batch::factory()->create();
        $student = Student::factory()->create(['status' => StudentStatus::Active]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $session = AttendanceSession::factory()->create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'taken_by' => $admin->id,
            'date' => now()->toDateString(),
            'subject_id' => Subject::factory(),
        ]);

        StudentAttendance::factory()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => StudentAttendanceStatus::Absent,
        ]);

        $leave = LeaveRequest::factory()->create([
            'requester_type' => Student::class,
            'requester_id' => $student->id,
            'from_date' => now()->toDateString(),
            'to_date' => now()->toDateString(),
            'status' => LeaveRequestStatus::Pending,
        ]);

        app(LeaveRequestService::class)->approve($leave, $admin);

        $this->assertSame(
            StudentAttendanceStatus::Excused,
            StudentAttendance::query()
                ->where('attendance_session_id', $session->id)
                ->where('student_id', $student->id)
                ->first()
                ->status,
        );
    }

    public function test_reports_compute_percentages_with_sql_aggregates(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();
        $batch = Batch::factory()->create();
        $student = Student::factory()->create(['status' => StudentStatus::Active, 'name' => 'Report Student']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'status' => EnrollmentStatus::Active,
        ]);

        foreach ([0, 1, 2, 3] as $offset) {
            $session = AttendanceSession::factory()->create([
                'batch_id' => $batch->id,
                'teacher_id' => $teacher->id,
                'taken_by' => $admin->id,
                'date' => now()->subDays($offset)->toDateString(),
                'subject_id' => Subject::factory(),
            ]);

            StudentAttendance::factory()->create([
                'attendance_session_id' => $session->id,
                'student_id' => $student->id,
                'status' => $offset === 0
                    ? StudentAttendanceStatus::Absent
                    : StudentAttendanceStatus::Present,
            ]);
        }

        $summary = app(AttendanceReportService::class)->studentSummaries($batch->id);
        $row = $summary->firstWhere('student_id', $student->id);

        $this->assertNotNull($row);
        $this->assertSame(4, (int) $row->total_days);
        $this->assertSame(3, (int) $row->attended_days);
        $this->assertEquals(75.0, (float) $row->attendance_percent);

        $absent = app(AttendanceReportService::class)->absentToday($batch->id, now());
        $this->assertTrue($absent->contains(fn ($item) => (int) $item->student_id === $student->id));

        $low = app(AttendanceReportService::class)->lowAttendance($batch->id, 80);
        $this->assertTrue($low->contains(fn ($item) => (int) $item->student_id === $student->id));
    }

    public function test_guardian_absent_notification_is_queued_when_enabled(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);
        $teacher = Teacher::factory()->create();
        $batch = Batch::factory()->create();
        $student = Student::factory()->create(['status' => StudentStatus::Active]);
        $guardian = \App\Models\Guardian::factory()->create();
        $student->guardians()->attach($guardian->id, ['relation' => 'father']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'status' => EnrollmentStatus::Active,
        ]);

        app(\App\Services\SettingsService::class)->set('attendance.notify_guardian_on_absent', true);

        app(AttendanceService::class)->saveBulk(
            actor: $admin,
            batch: $batch,
            date: now(),
            rows: [['student_id' => $student->id, 'status' => StudentAttendanceStatus::Absent->value]],
            teacherId: $teacher->id,
        );

        Notification::assertSentTo($guardian, \App\Notifications\StudentAbsentNotification::class);
    }
}
