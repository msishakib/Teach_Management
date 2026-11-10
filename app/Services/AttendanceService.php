<?php

namespace App\Services;

use App\Enums\AttendanceSessionStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\StudentAttendanceStatus;
use App\Enums\StudentStatus;
use App\Models\AttendanceSession;
use App\Models\Batch;
use App\Models\ClassRoutine;
use App\Models\LeaveRequest;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\StudentAbsentNotification;
use App\Support\PermissionName;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    /**
     * Active enrolled students for a batch in a single query, with existing
     * attendance and approved-leave defaults applied in SQL.
     *
     * @return EloquentCollection<int, Student>
     */
    public function loadRoster(Batch $batch, CarbonInterface $date, ?int $subjectId = null): EloquentCollection
    {
        $dateString = $date->toDateString();

        $sessionId = AttendanceSession::query()
            ->where('batch_id', $batch->id)
            ->whereDate('date', $dateString)
            ->when(
                $subjectId === null,
                fn ($q) => $q->whereNull('subject_id'),
                fn ($q) => $q->where('subject_id', $subjectId),
            )
            ->value('id');

        return Student::query()
            ->select([
                'students.id',
                'students.admission_no',
                'students.name',
                'students.phone',
                'students.status',
                'student_attendances.status as attendance_status',
                'student_attendances.remarks as attendance_remarks',
                DB::raw('CASE WHEN approved_leaves.id IS NOT NULL THEN 1 ELSE 0 END as on_approved_leave'),
            ])
            ->join('enrollments', function ($join) use ($batch) {
                $join->on('enrollments.student_id', '=', 'students.id')
                    ->where('enrollments.batch_id', '=', $batch->id)
                    ->where('enrollments.status', '=', EnrollmentStatus::Active->value);
            })
            ->leftJoin('student_attendances', function ($join) use ($sessionId) {
                $join->on('student_attendances.student_id', '=', 'students.id');

                if ($sessionId) {
                    $join->where('student_attendances.attendance_session_id', '=', $sessionId);
                } else {
                    $join->whereRaw('1 = 0');
                }
            })
            ->leftJoin('leave_requests as approved_leaves', function ($join) use ($dateString) {
                $join->on('approved_leaves.requester_id', '=', 'students.id')
                    ->where('approved_leaves.requester_type', '=', Student::class)
                    ->where('approved_leaves.status', '=', LeaveRequestStatus::Approved->value)
                    ->whereDate('approved_leaves.from_date', '<=', $dateString)
                    ->whereDate('approved_leaves.to_date', '>=', $dateString);
            })
            ->where('students.status', StudentStatus::Active->value)
            ->whereNull('students.deleted_at')
            ->orderBy('students.name')
            ->get();
    }

    /**
     * @param  array<int, array{student_id: int, status: string, remarks?: ?string}>  $rows
     */
    public function saveBulk(
        User $actor,
        Batch $batch,
        CarbonInterface $date,
        array $rows,
        ?int $subjectId = null,
        ?int $teacherId = null,
        ?string $startTime = null,
        bool $finalize = false,
    ): AttendanceSession {
        $this->assertNotFuture($date);
        $this->assertCanTake($actor, $batch);

        return DB::transaction(function () use ($actor, $batch, $date, $rows, $subjectId, $teacherId, $startTime, $finalize) {
            $session = $this->findOrCreateSession($actor, $batch, $date, $subjectId, $teacherId, $startTime);

            $this->assertCanEditSession($actor, $session);

            $allowedStudentIds = $this->activeEnrolledStudentIds($batch->id);
            $leaveStudentIds = $this->approvedLeaveStudentIds($allowedStudentIds, $date);

            $now = now();
            $payload = [];
            $absentStudentIds = [];

            foreach ($rows as $row) {
                $studentId = (int) $row['student_id'];

                if (! isset($allowedStudentIds[$studentId])) {
                    continue;
                }

                $status = (string) ($row['status'] ?? StudentAttendanceStatus::Present->value);

                if (isset($leaveStudentIds[$studentId])) {
                    $status = StudentAttendanceStatus::Excused->value;
                }

                if (! StudentAttendanceStatus::tryFrom($status)) {
                    throw ValidationException::withMessages([
                        'rows' => "Invalid attendance status [{$status}] for student {$studentId}.",
                    ]);
                }

                if ($status === StudentAttendanceStatus::Absent->value) {
                    $absentStudentIds[] = $studentId;
                }

                $payload[] = [
                    'attendance_session_id' => $session->id,
                    'student_id' => $studentId,
                    'status' => $status,
                    'remarks' => $row['remarks'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($payload !== []) {
                StudentAttendance::query()->upsert(
                    $payload,
                    ['attendance_session_id', 'student_id'],
                    ['status', 'remarks', 'updated_at'],
                );
            }

            if ($finalize) {
                $session->update(['status' => AttendanceSessionStatus::Finalized]);
            }

            $this->maybeNotifyGuardiansOfAbsence($session->fresh() ?? $session, $absentStudentIds);

            return $session->fresh(['studentAttendances', 'batch', 'teacher']) ?? $session;
        });
    }

    public function finalize(User $actor, AttendanceSession $session): AttendanceSession
    {
        $this->assertCanTake($actor, $session->batch);
        $this->assertCanEditSession($actor, $session);

        $session->update(['status' => AttendanceSessionStatus::Finalized]);

        return $session->refresh();
    }

    public function teacherCanAccessBatch(User $user, Batch $batch): bool
    {
        if ($user->can(PermissionName::make('attendance', 'override'))) {
            return true;
        }

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        $teacher = $user->teacher;

        if (! $teacher) {
            return false;
        }

        return ClassRoutine::query()
            ->where('teacher_id', $teacher->id)
            ->where('batch_id', $batch->id)
            ->exists();
    }

    /**
     * @return Collection<int, Batch>
     */
    public function batchesForActor(User $user): Collection
    {
        $query = Batch::query()->orderBy('name');

        if ($user->hasAnyRole(['super_admin', 'admin']) || $user->can(PermissionName::make('attendance', 'override'))) {
            return $query->get();
        }

        $teacher = $user->teacher;

        if (! $teacher) {
            return collect();
        }

        return $query
            ->whereIn('id', $teacher->assignedBatchIds())
            ->get();
    }

    /**
     * Mark existing student attendance rows as excused for an approved leave window.
     */
    public function applyApprovedLeave(LeaveRequest $leave): void
    {
        if ($leave->requester_type !== Student::class || $leave->status !== LeaveRequestStatus::Approved) {
            return;
        }

        $studentId = (int) $leave->requester_id;

        $sessionIds = AttendanceSession::query()
            ->whereDate('date', '>=', $leave->from_date)
            ->whereDate('date', '<=', $leave->to_date)
            ->whereHas('batch.enrollments', function ($query) use ($studentId) {
                $query->where('student_id', $studentId)
                    ->where('status', EnrollmentStatus::Active->value);
            })
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $sessionIds->map(fn ($sessionId) => [
            'attendance_session_id' => $sessionId,
            'student_id' => $studentId,
            'status' => StudentAttendanceStatus::Excused->value,
            'remarks' => 'Auto-marked from approved leave',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        StudentAttendance::query()->upsert(
            $rows,
            ['attendance_session_id', 'student_id'],
            ['status', 'remarks', 'updated_at'],
        );
    }

    protected function findOrCreateSession(
        User $actor,
        Batch $batch,
        CarbonInterface $date,
        ?int $subjectId,
        ?int $teacherId,
        ?string $startTime,
    ): AttendanceSession {
        $teacherId ??= $actor->teacher?->id;

        if (! $teacherId) {
            $teacherId = ClassRoutine::query()
                ->where('batch_id', $batch->id)
                ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
                ->value('teacher_id');
        }

        if (! $teacherId) {
            throw ValidationException::withMessages([
                'teacher_id' => 'A teacher is required to take attendance for this batch.',
            ]);
        }

        $query = AttendanceSession::query()
            ->where('batch_id', $batch->id)
            ->whereDate('date', $date->toDateString())
            ->when(
                $subjectId === null,
                fn ($q) => $q->whereNull('subject_id'),
                fn ($q) => $q->where('subject_id', $subjectId),
            );

        $existing = $query->lockForUpdate()->first();

        if ($existing) {
            return $existing;
        }

        try {
            return AttendanceSession::query()->create([
                'batch_id' => $batch->id,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId,
                'date' => $date->toDateString(),
                'start_time' => $startTime,
                'taken_by' => $actor->id,
                'status' => AttendanceSessionStatus::Open,
            ]);
        } catch (\Illuminate\Database\QueryException $exception) {
            $existing = $query->first();

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    /**
     * @return array<int, true>
     */
    protected function activeEnrolledStudentIds(int $batchId): array
    {
        return Student::query()
            ->join('enrollments', 'enrollments.student_id', '=', 'students.id')
            ->where('enrollments.batch_id', $batchId)
            ->where('enrollments.status', EnrollmentStatus::Active->value)
            ->where('students.status', StudentStatus::Active->value)
            ->whereNull('students.deleted_at')
            ->pluck('students.id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * @param  array<int, true>  $studentIds
     * @return array<int, true>
     */
    protected function approvedLeaveStudentIds(array $studentIds, CarbonInterface $date): array
    {
        if ($studentIds === []) {
            return [];
        }

        return LeaveRequest::query()
            ->where('requester_type', Student::class)
            ->whereIn('requester_id', array_keys($studentIds))
            ->where('status', LeaveRequestStatus::Approved)
            ->whereDate('from_date', '<=', $date->toDateString())
            ->whereDate('to_date', '>=', $date->toDateString())
            ->pluck('requester_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * @param  list<int>  $absentStudentIds
     */
    protected function maybeNotifyGuardiansOfAbsence(AttendanceSession $session, array $absentStudentIds): void
    {
        if ($absentStudentIds === [] || ! (bool) setting('attendance.notify_guardian_on_absent', false)) {
            return;
        }

        $students = Student::query()
            ->with('guardians')
            ->whereIn('id', $absentStudentIds)
            ->get();

        foreach ($students as $student) {
            if ($student->guardians->isEmpty()) {
                continue;
            }

            Notification::send(
                $student->guardians,
                new StudentAbsentNotification($student, $session),
            );
        }
    }

    protected function assertNotFuture(CarbonInterface $date): void
    {
        if ($date->startOfDay()->greaterThan(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'date' => 'Attendance cannot be taken for future dates.',
            ]);
        }
    }

    protected function assertCanTake(User $actor, Batch $batch): void
    {
        if (! $actor->can(PermissionName::make('attendance', 'create'))
            && ! $actor->can(PermissionName::make('attendance', 'update'))) {
            throw ValidationException::withMessages([
                'batch_id' => 'You are not allowed to take attendance.',
            ]);
        }

        if (! $this->teacherCanAccessBatch($actor, $batch)) {
            throw ValidationException::withMessages([
                'batch_id' => 'You can only take attendance for batches assigned to you.',
            ]);
        }
    }

    protected function assertCanEditSession(User $actor, AttendanceSession $session): void
    {
        if (! $session->isFinalized()) {
            return;
        }

        if ($actor->can(PermissionName::make('attendance', 'override'))) {
            return;
        }

        throw ValidationException::withMessages([
            'session' => 'This attendance session is finalized and cannot be edited.',
        ]);
    }
}
