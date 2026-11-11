<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Enums\StudentStatus;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamMarkLog;
use App\Models\ExamSubject;
use App\Models\Student;
use App\Models\User;
use App\Support\PermissionName;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamMarksService
{
    /**
     * Active enrolled students for the exam batch with existing marks, one query.
     *
     * @return EloquentCollection<int, Student>
     */
    public function loadRoster(ExamSubject $examSubject): EloquentCollection
    {
        $examSubject->loadMissing('exam');

        return Student::query()
            ->select([
                'students.id',
                'students.admission_no',
                'students.name',
                'students.status',
                'exam_marks.id as exam_mark_id',
                'exam_marks.marks_obtained',
                'exam_marks.is_absent',
                'exam_marks.remarks as mark_remarks',
            ])
            ->join('enrollments', function ($join) use ($examSubject) {
                $join->on('enrollments.student_id', '=', 'students.id')
                    ->where('enrollments.batch_id', '=', $examSubject->exam->batch_id)
                    ->where('enrollments.status', '=', EnrollmentStatus::Active->value);
            })
            ->leftJoin('exam_marks', function ($join) use ($examSubject) {
                $join->on('exam_marks.student_id', '=', 'students.id')
                    ->where('exam_marks.exam_subject_id', '=', $examSubject->id);
            })
            ->where('students.status', StudentStatus::Active->value)
            ->whereNull('students.deleted_at')
            ->orderBy('students.name')
            ->get();
    }

    /**
     * @param  array<int, array{student_id: int, marks_obtained?: mixed, is_absent?: bool|int|string, remarks?: ?string}>  $rows
     * @return array{saved: int, created: int, updated: int}
     */
    public function saveBulk(
        User $actor,
        ExamSubject $examSubject,
        array $rows,
        ?string $reason = null,
    ): array {
        $examSubject->loadMissing(['exam', 'subject']);
        $this->assertCanEnterMarks($actor, $examSubject);

        $fullMarks = (string) $examSubject->full_marks;
        $allowedStudentIds = $this->activeEnrolledStudentIds($examSubject->exam->batch_id);
        $requiresOverride = $this->requiresOverride($examSubject->exam);

        if ($requiresOverride && blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required to edit marks after the exam is published.',
            ]);
        }

        return DB::transaction(function () use ($actor, $examSubject, $rows, $reason, $fullMarks, $allowedStudentIds, $requiresOverride) {
            /** @var Collection<int, ExamMark> $existing */
            $existing = ExamMark::query()
                ->where('exam_subject_id', $examSubject->id)
                ->get()
                ->keyBy('student_id');

            $now = now();
            $upsertRows = [];
            $changedStudentIds = [];
            $created = 0;
            $updated = 0;

            foreach ($rows as $index => $row) {
                $studentId = (int) ($row['student_id'] ?? 0);

                if (! isset($allowedStudentIds[$studentId])) {
                    continue;
                }

                $isAbsent = filter_var($row['is_absent'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $marks = $this->normalizeMarks($row['marks_obtained'] ?? null, $isAbsent, $fullMarks, $index);
                $remarks = array_key_exists('remarks', $row) ? ($row['remarks'] !== null ? (string) $row['remarks'] : null) : null;

                /** @var ExamMark|null $current */
                $current = $existing->get($studentId);

                if ($current) {
                    $oldMarks = $current->marks_obtained !== null ? (string) $current->marks_obtained : null;
                    $changed = $current->is_absent !== $isAbsent
                        || ! $this->marksEqual($oldMarks, $marks)
                        || (string) ($current->remarks ?? '') !== (string) ($remarks ?? '');

                    if (! $changed) {
                        continue;
                    }

                    $updated++;
                } else {
                    // Skip empty brand-new rows (no mark, not absent).
                    if (! $isAbsent && $marks === null && blank($remarks)) {
                        continue;
                    }

                    $created++;
                }

                $changedStudentIds[] = $studentId;
                $upsertRows[] = [
                    'exam_subject_id' => $examSubject->id,
                    'student_id' => $studentId,
                    'marks_obtained' => $marks,
                    'is_absent' => $isAbsent ? 1 : 0,
                    'remarks' => $remarks,
                    'entered_by' => $current?->entered_by ?? $actor->id,
                    'updated_by' => $actor->id,
                    'created_at' => $current?->created_at ?? $now,
                    'updated_at' => $now,
                ];
            }

            if ($upsertRows !== []) {
                ExamMark::query()->upsert(
                    $upsertRows,
                    ['exam_subject_id', 'student_id'],
                    ['marks_obtained', 'is_absent', 'remarks', 'updated_by', 'updated_at'],
                );

                $this->writeAuditLogs(
                    actor: $actor,
                    examSubject: $examSubject,
                    existing: $existing,
                    changedStudentIds: $changedStudentIds,
                    reason: $reason,
                    forceReason: $requiresOverride,
                    now: $now,
                );
            }

            return [
                'saved' => count($upsertRows),
                'created' => $created,
                'updated' => $updated,
            ];
        });
    }

    /**
     * @param  array{marks_obtained?: mixed, is_absent?: bool|int|string, remarks?: ?string}  $attributes
     */
    public function saveOne(
        User $actor,
        ExamSubject $examSubject,
        int $studentId,
        array $attributes,
        ?string $reason = null,
    ): ExamMark {
        $this->saveBulk($actor, $examSubject, [[
            'student_id' => $studentId,
            'marks_obtained' => $attributes['marks_obtained'] ?? null,
            'is_absent' => $attributes['is_absent'] ?? false,
            'remarks' => $attributes['remarks'] ?? null,
        ]], $reason);

        return ExamMark::query()
            ->where('exam_subject_id', $examSubject->id)
            ->where('student_id', $studentId)
            ->firstOrFail();
    }

    public function assertCanEnterMarks(User $actor, ExamSubject $examSubject): void
    {
        $examSubject->loadMissing('exam');
        $exam = $examSubject->exam;

        if (! $this->teacherCanAccessExamSubject($actor, $examSubject)) {
            throw ValidationException::withMessages([
                'exam_subject_id' => 'You are not assigned to enter marks for this exam subject.',
            ]);
        }

        if ($exam->status === ExamStatus::MarksEntry) {
            return;
        }

        if ($this->requiresOverride($exam)) {
            if (! $actor->can(PermissionName::make('exam', 'override'))) {
                throw ValidationException::withMessages([
                    'status' => 'Marks are locked after publish. exam.override permission is required.',
                ]);
            }

            return;
        }

        throw ValidationException::withMessages([
            'status' => 'Marks can only be entered while the exam status is Marks entry.',
        ]);
    }

    public function teacherCanAccessExamSubject(User $user, ExamSubject $examSubject): bool
    {
        if ($user->can(PermissionName::make('exam', 'override'))) {
            return true;
        }

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        $teacher = $user->teacher;

        if (! $teacher) {
            return false;
        }

        return (int) $examSubject->teacher_id === (int) $teacher->id;
    }

    /**
     * @return Collection<int, Exam>
     */
    public function examsForActor(User $user): Collection
    {
        $query = Exam::query()
            ->with(['examType', 'batch'])
            ->whereIn('status', [
                ExamStatus::MarksEntry->value,
                ExamStatus::Published->value,
                ExamStatus::Closed->value,
            ])
            ->orderByDesc('start_date');

        if ($user->can(PermissionName::make('exam', 'override'))
            || $user->hasAnyRole(['super_admin', 'admin'])) {
            return $query->get();
        }

        $teacher = $user->teacher;

        if (! $teacher) {
            return collect();
        }

        return $query
            ->whereHas('subjects', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->get();
    }

    /**
     * @return Collection<int, ExamSubject>
     */
    public function subjectsForActor(User $user, Exam $exam): Collection
    {
        $query = ExamSubject::query()
            ->with(['subject', 'teacher'])
            ->where('exam_id', $exam->id)
            ->orderBy('exam_date');

        if ($user->can(PermissionName::make('exam', 'override'))
            || $user->hasAnyRole(['super_admin', 'admin'])) {
            return $query->get();
        }

        $teacher = $user->teacher;

        if (! $teacher) {
            return collect();
        }

        return $query->where('teacher_id', $teacher->id)->get();
    }

    /**
     * @return array{subjects_total: int, subjects_complete: int, enrolled: int, percent: float}
     */
    public function progress(Exam $exam): array
    {
        return $this->progressForExams(collect([$exam]))[$exam->id];
    }

    /**
     * @param  Collection<int, Exam>  $exams
     * @return array<int, array{subjects_total: int, subjects_complete: int, enrolled: int, percent: float}>
     */
    public function progressForExams(Collection $exams): array
    {
        if ($exams->isEmpty()) {
            return [];
        }

        $batchIds = $exams->pluck('batch_id')->unique()->all();
        $enrolledByBatch = Student::query()
            ->selectRaw('enrollments.batch_id, COUNT(students.id) as enrolled_count')
            ->join('enrollments', 'enrollments.student_id', '=', 'students.id')
            ->whereIn('enrollments.batch_id', $batchIds)
            ->where('enrollments.status', EnrollmentStatus::Active->value)
            ->where('students.status', StudentStatus::Active->value)
            ->whereNull('students.deleted_at')
            ->groupBy('enrollments.batch_id')
            ->pluck('enrolled_count', 'batch_id');

        $subjects = ExamSubject::query()
            ->whereIn('exam_id', $exams->pluck('id'))
            ->withCount([
                'marks as completed_marks_count' => function ($query) {
                    $query->where(function ($inner) {
                        $inner->where('is_absent', true)
                            ->orWhereNotNull('marks_obtained');
                    });
                },
            ])
            ->get()
            ->groupBy('exam_id');

        $result = [];

        foreach ($exams as $exam) {
            $enrolled = (int) ($enrolledByBatch[$exam->batch_id] ?? 0);
            $examSubjects = $subjects->get($exam->id, collect());
            $total = $examSubjects->count();
            $complete = $examSubjects->filter(
                fn (ExamSubject $subject) => $enrolled > 0 && (int) $subject->completed_marks_count >= $enrolled
            )->count();

            $result[$exam->id] = [
                'subjects_total' => $total,
                'subjects_complete' => $complete,
                'enrolled' => $enrolled,
                'percent' => $total === 0 ? 0.0 : round(($complete / $total) * 100, 1),
            ];
        }

        return $result;
    }

    /**
     * @return list<list<string|int|float|null>>
     */
    public function csvTemplateRows(ExamSubject $examSubject): array
    {
        $rows = [[
            'admission_no',
            'student_name',
            'marks_obtained',
            'is_absent',
            'remarks',
        ]];

        foreach ($this->loadRoster($examSubject) as $student) {
            $rows[] = [
                $student->admission_no,
                $student->name,
                $student->marks_obtained,
                ((int) ($student->is_absent ?? 0) === 1) ? '1' : '0',
                $student->mark_remarks,
            ];
        }

        return $rows;
    }

    public function requiresOverride(Exam $exam): bool
    {
        return in_array($exam->status, [ExamStatus::Published, ExamStatus::Closed], true);
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

    protected function normalizeMarks(mixed $marksRaw, bool $isAbsent, string $fullMarks, int $index): ?string
    {
        if ($isAbsent) {
            if ($marksRaw !== null && $marksRaw !== '') {
                throw ValidationException::withMessages([
                    "rows.{$index}.marks_obtained" => 'Absent students must have null marks.',
                ]);
            }

            return null;
        }

        if ($marksRaw === null || $marksRaw === '') {
            return null;
        }

        if (! is_numeric($marksRaw)) {
            throw ValidationException::withMessages([
                "rows.{$index}.marks_obtained" => 'Marks must be a number.',
            ]);
        }

        $marks = number_format((float) $marksRaw, 2, '.', '');

        if (bccomp($marks, '0', 2) < 0) {
            throw ValidationException::withMessages([
                "rows.{$index}.marks_obtained" => 'Marks cannot be negative.',
            ]);
        }

        if (bccomp($marks, $fullMarks, 2) > 0) {
            throw ValidationException::withMessages([
                "rows.{$index}.marks_obtained" => "Marks cannot exceed full marks ({$fullMarks}).",
            ]);
        }

        return $marks;
    }

    protected function marksEqual(?string $a, ?string $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }

        if ($a === null || $b === null) {
            return false;
        }

        return bccomp($a, $b, 2) === 0;
    }

    /**
     * @param  Collection<int, ExamMark>  $existing
     * @param  list<int>  $changedStudentIds
     */
    protected function writeAuditLogs(
        User $actor,
        ExamSubject $examSubject,
        Collection $existing,
        array $changedStudentIds,
        ?string $reason,
        bool $forceReason,
        mixed $now,
    ): void {
        $freshMarks = ExamMark::query()
            ->where('exam_subject_id', $examSubject->id)
            ->whereIn('student_id', $changedStudentIds)
            ->get()
            ->keyBy('student_id');

        $logs = [];

        foreach ($changedStudentIds as $studentId) {
            /** @var ExamMark|null $after */
            $after = $freshMarks->get($studentId);

            if (! $after) {
                continue;
            }

            /** @var ExamMark|null $before */
            $before = $existing->get($studentId);

            // During marks_entry, only log updates (not first inserts).
            // After publish/override, log every change including first inserts.
            if (! $before && ! $forceReason) {
                continue;
            }

            $oldMarks = $before?->marks_obtained !== null ? (string) $before->marks_obtained : null;
            $newMarks = $after->marks_obtained !== null ? (string) $after->marks_obtained : null;

            $logs[] = [
                'exam_mark_id' => $after->id,
                'old_marks' => $oldMarks,
                'new_marks' => $newMarks,
                'changed_by' => $actor->id,
                'reason' => $reason,
                'created_at' => $now,
            ];
        }

        if ($logs !== []) {
            ExamMarkLog::query()->insert($logs);
        }
    }
}
