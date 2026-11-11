<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamResultStatus;
use App\Enums\ExamStatus;
use App\Enums\StudentStatus;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamResult;
use App\Models\ExamResultItem;
use App\Models\ExamSubject;
use App\Models\GradingScale;
use App\Models\GradingScaleRule;
use App\Models\ResultVersion;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ExamResultPublishedNotification;
use App\Support\PermissionName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ResultService
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    /**
     * Process exam results in chunks. Creates a new result_version snapshot.
     *
     * @return array{version: ResultVersion, processed: int, failed: int, passed: int, absent: int}
     */
    public function process(Exam $exam, ?User $actor = null, int $chunkSize = 250): array
    {
        $exam->loadMissing(['subjects', 'gradingScale.rules', 'batch']);

        if (! in_array($exam->status, [ExamStatus::MarksEntry, ExamStatus::Published], true)) {
            throw ValidationException::withMessages([
                'status' => 'Results can only be processed while the exam is in marks entry (or when reprocessing a published exam with override).',
            ]);
        }

        if ($exam->status === ExamStatus::Published && $actor && ! $actor->can(PermissionName::make('exam', 'override'))) {
            throw ValidationException::withMessages([
                'status' => 'Re-processing a published exam requires exam.override.',
            ]);
        }

        $missing = $this->missingMarksSubjects($exam);

        if ($missing->isNotEmpty()) {
            $labels = $missing->map(fn (ExamSubject $subject) => $subject->subject?->name ?? "#{$subject->id}")->implode(', ');

            throw ValidationException::withMessages([
                'marks' => "Cannot process results. Incomplete marks for: {$labels}.",
            ]);
        }

        if ($exam->subjects->isEmpty()) {
            throw ValidationException::withMessages([
                'subjects' => 'Exam has no subjects to process.',
            ]);
        }

        $scale = $this->resolveGradingScale($exam);
        $rules = $scale->rules->sortByDesc('min_percent')->values();
        $failIfAny = (bool) $this->settings->get('academic.fail_if_any_subject_fails', true);
        $gpaZeroOnFail = (bool) $this->settings->get('academic.gpa_zero_on_fail', true);

        return DB::transaction(function () use ($exam, $actor, $chunkSize, $rules, $failIfAny, $gpaZeroOnFail, $scale) {
            $wasPublished = $exam->results()->withoutGlobalScopes()->whereNotNull('published_at')->exists();

            $nextVersion = ((int) ResultVersion::query()->where('exam_id', $exam->id)->max('version')) + 1;

            $version = ResultVersion::query()->create([
                'exam_id' => $exam->id,
                'version' => $nextVersion,
                'processed_by' => $actor?->id,
                'processed_at' => now(),
                'snapshot' => [
                    'grading_scale_id' => $scale->id,
                    'grading_scale_name' => $scale->name,
                    'fail_if_any_subject_fails' => $failIfAny,
                    'gpa_zero_on_fail' => $gpaZeroOnFail,
                    'subject_ids' => $exam->subjects->pluck('id')->all(),
                    'enrolled' => $this->enrolledStudentCount($exam),
                ],
                'notes' => "Processed version {$nextVersion}",
            ]);

            // Clear previous result items then results for this exam (reprocess).
            $existingIds = ExamResult::withoutGlobalScopes()
                ->where('exam_id', $exam->id)
                ->pluck('id');

            if ($existingIds->isNotEmpty()) {
                ExamResultItem::query()->whereIn('exam_result_id', $existingIds)->delete();
                ExamResult::withoutGlobalScopes()->where('exam_id', $exam->id)->delete();
            }

            $subjects = $exam->subjects->keyBy('id');
            $fullMarksTotal = $subjects->sum(fn (ExamSubject $s) => (float) $s->full_marks);

            $processed = 0;
            $passed = 0;
            $failed = 0;
            $absent = 0;
            $rankRows = [];

            $studentIds = Student::query()
                ->join('enrollments', function ($join) use ($exam) {
                    $join->on('enrollments.student_id', '=', 'students.id')
                        ->where('enrollments.batch_id', '=', $exam->batch_id)
                        ->where('enrollments.status', '=', EnrollmentStatus::Active->value);
                })
                ->where('students.status', StudentStatus::Active->value)
                ->whereNull('students.deleted_at')
                ->orderBy('students.id')
                ->pluck('students.id');

            foreach ($studentIds->chunk($chunkSize) as $chunkIds) {
                $marks = ExamMark::query()
                    ->whereIn('exam_subject_id', $subjects->keys())
                    ->whereIn('student_id', $chunkIds->all())
                    ->get()
                    ->groupBy('student_id');

                foreach ($chunkIds as $studentId) {
                    $studentMarks = $marks->get($studentId, collect())->keyBy('exam_subject_id');
                    $itemRows = [];
                    $totalObtained = '0.00';
                    $gradePoints = [];
                    $anyFail = false;
                    $allAbsent = $subjects->isNotEmpty();

                    foreach ($subjects as $subject) {
                        /** @var ExamMark|null $mark */
                        $mark = $studentMarks->get($subject->id);
                        $isAbsent = (bool) ($mark?->is_absent);
                        $obtained = $isAbsent ? null : ($mark?->marks_obtained !== null ? (string) $mark->marks_obtained : null);

                        if (! $isAbsent) {
                            $allAbsent = false;
                        }

                        $percent = null;
                        if ($obtained !== null && bccomp((string) $subject->full_marks, '0', 2) > 0) {
                            $percent = (float) bcmul(bcdiv($obtained, (string) $subject->full_marks, 6), '100', 4);
                        }

                        $grade = $this->resolveGrade($rules, $percent, $isAbsent || $obtained === null);
                        $isPass = ! $isAbsent
                            && $obtained !== null
                            && bccomp($obtained, (string) $subject->pass_marks, 2) >= 0;

                        if (! $isPass) {
                            $anyFail = true;
                        }

                        if ($obtained !== null) {
                            $totalObtained = bcadd($totalObtained, $obtained, 2);
                        }

                        $gradePoints[] = (float) $grade['grade_point'];

                        $itemRows[] = [
                            'exam_subject_id' => $subject->id,
                            'marks_obtained' => $obtained,
                            'letter_grade' => $grade['letter_grade'],
                            'grade_point' => $grade['grade_point'],
                            'is_pass' => $isPass ? 1 : 0,
                            'is_absent' => $isAbsent ? 1 : 0,
                        ];
                    }

                    if ($allAbsent) {
                        $status = ExamResultStatus::Absent;
                        $gpa = '0.00';
                        $letter = 'F';
                        $absent++;
                    } elseif ($failIfAny && $anyFail) {
                        $status = ExamResultStatus::Fail;
                        $gpa = $gpaZeroOnFail ? '0.00' : $this->averageGradePoint($gradePoints);
                        $letter = $gpaZeroOnFail
                            ? 'F'
                            : $this->resolveGrade($rules, $this->percentage($totalObtained, $fullMarksTotal), false)['letter_grade'];
                        $failed++;
                    } else {
                        $status = $anyFail ? ExamResultStatus::Fail : ExamResultStatus::Pass;
                        $gpa = ($anyFail && $gpaZeroOnFail) ? '0.00' : $this->averageGradePoint($gradePoints);
                        $letter = ($anyFail && $gpaZeroOnFail)
                            ? 'F'
                            : $this->resolveGrade($rules, $this->percentage($totalObtained, $fullMarksTotal), false)['letter_grade'];
                        if ($status === ExamResultStatus::Pass) {
                            $passed++;
                        } else {
                            $failed++;
                        }
                    }

                    $percentage = number_format($this->percentage($totalObtained, $fullMarksTotal), 2, '.', '');

                    $result = ExamResult::withoutGlobalScopes()->create([
                        'exam_id' => $exam->id,
                        'student_id' => $studentId,
                        'result_version_id' => $version->id,
                        'total_marks' => $totalObtained,
                        'full_marks' => number_format((float) $fullMarksTotal, 2, '.', ''),
                        'percentage' => $percentage,
                        'gpa' => $gpa,
                        'letter_grade' => $letter,
                        'status' => $status,
                        'rank_in_batch' => null,
                        'published_at' => $wasPublished ? now() : null,
                    ]);

                    foreach ($itemRows as $item) {
                        $result->items()->create($item);
                    }

                    $rankRows[] = [
                        'id' => $result->id,
                        'total_marks' => (float) $totalObtained,
                        'status' => $status->value,
                    ];

                    $processed++;
                }
            }

            $this->assignRanks($rankRows);

            $version->update([
                'snapshot' => array_merge($version->snapshot ?? [], [
                    'processed' => $processed,
                    'passed' => $passed,
                    'failed' => $failed,
                    'absent' => $absent,
                ]),
            ]);

            return [
                'version' => $version->fresh(),
                'processed' => $processed,
                'failed' => $failed,
                'passed' => $passed,
                'absent' => $absent,
            ];
        });
    }

    public function publish(Exam $exam, User $actor, bool $notify = false): Exam
    {
        if (! $actor->can(PermissionName::make('exam', 'publish'))) {
            throw ValidationException::withMessages([
                'permission' => 'You do not have permission to publish exam results.',
            ]);
        }

        if ($exam->status !== ExamStatus::MarksEntry && $exam->status !== ExamStatus::Published) {
            throw ValidationException::withMessages([
                'status' => 'Exam must be in marks entry before publishing results.',
            ]);
        }

        $resultsCount = ExamResult::withoutGlobalScopes()->where('exam_id', $exam->id)->count();

        if ($resultsCount === 0) {
            throw ValidationException::withMessages([
                'results' => 'Process results before publishing.',
            ]);
        }

        $missing = $this->missingMarksSubjects($exam);

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'marks' => 'Cannot publish while marks are incomplete.',
            ]);
        }

        return DB::transaction(function () use ($exam, $actor, $notify) {
            ExamResult::withoutGlobalScopes()
                ->where('exam_id', $exam->id)
                ->whereNull('published_at')
                ->update(['published_at' => now()]);

            if ($exam->status === ExamStatus::MarksEntry) {
                $exam->update(['status' => ExamStatus::Published]);
            }

            if ($notify) {
                $this->notifyPublished($exam);
            }

            return $exam->refresh();
        });
    }

    public function unpublish(Exam $exam, User $actor): Exam
    {
        if (! $actor->can(PermissionName::make('exam', 'override'))) {
            throw ValidationException::withMessages([
                'permission' => 'Unpublishing results requires exam.override.',
            ]);
        }

        return DB::transaction(function () use ($exam) {
            ExamResult::withoutGlobalScopes()
                ->where('exam_id', $exam->id)
                ->update(['published_at' => null]);

            if ($exam->status === ExamStatus::Published) {
                $exam->update(['status' => ExamStatus::MarksEntry]);
            }

            return $exam->refresh();
        });
    }

    /**
     * Subjects that do not yet have a complete mark for every active enrolled student.
     *
     * @return Collection<int, ExamSubject>
     */
    public function missingMarksSubjects(Exam $exam): Collection
    {
        $enrolled = $this->enrolledStudentCount($exam);

        if ($enrolled === 0) {
            return collect();
        }

        return ExamSubject::query()
            ->with('subject')
            ->where('exam_id', $exam->id)
            ->withCount([
                'marks as completed_marks_count' => function ($query) {
                    $query->where(function ($inner) {
                        $inner->where('is_absent', true)
                            ->orWhereNotNull('marks_obtained');
                    });
                },
            ])
            ->get()
            ->filter(fn (ExamSubject $subject) => (int) $subject->completed_marks_count < $enrolled)
            ->values();
    }

    /**
     * @return array{highest: float, average: float, pass_rate: float, total: int, passed: int}
     */
    public function subjectStats(ExamSubject $examSubject): array
    {
        $items = ExamResultItem::query()
            ->where('exam_subject_id', $examSubject->id)
            ->whereHas('examResult', fn ($q) => $q->withoutGlobalScopes()->where('exam_id', $examSubject->exam_id))
            ->get();

        $scored = $items->filter(fn (ExamResultItem $item) => ! $item->is_absent && $item->marks_obtained !== null);

        $total = $items->count();
        $passed = $items->where('is_pass', true)->count();

        return [
            'highest' => $scored->isEmpty() ? 0.0 : (float) $scored->max('marks_obtained'),
            'average' => $scored->isEmpty() ? 0.0 : round((float) $scored->avg('marks_obtained'), 2),
            'pass_rate' => $total === 0 ? 0.0 : round(($passed / $total) * 100, 2),
            'total' => $total,
            'passed' => $passed,
        ];
    }

    /**
     * @return Collection<int, ExamResult>
     */
    public function meritList(Exam $exam, bool $publishedOnly = false): Collection
    {
        $query = ExamResult::withoutGlobalScopes()
            ->with(['student', 'items.examSubject.subject'])
            ->where('exam_id', $exam->id)
            ->where('status', '!=', ExamResultStatus::Absent->value)
            ->orderByRaw('rank_in_batch is null')
            ->orderBy('rank_in_batch')
            ->orderByDesc('total_marks');

        if ($publishedOnly) {
            $query->whereNotNull('published_at');
        }

        return $query->get();
    }

    /**
     * @return Collection<int, ExamResult>
     */
    public function failList(Exam $exam, bool $publishedOnly = false): Collection
    {
        $query = ExamResult::withoutGlobalScopes()
            ->with(['student', 'items.examSubject.subject'])
            ->where('exam_id', $exam->id)
            ->where('status', ExamResultStatus::Fail->value)
            ->orderBy('student_id');

        if ($publishedOnly) {
            $query->whereNotNull('published_at');
        }

        return $query->get();
    }

    protected function enrolledStudentCount(Exam $exam): int
    {
        return Student::query()
            ->join('enrollments', 'enrollments.student_id', '=', 'students.id')
            ->where('enrollments.batch_id', $exam->batch_id)
            ->where('enrollments.status', EnrollmentStatus::Active->value)
            ->where('students.status', StudentStatus::Active->value)
            ->whereNull('students.deleted_at')
            ->count();
    }

    protected function resolveGradingScale(Exam $exam): GradingScale
    {
        if ($exam->grading_scale_id) {
            $scale = GradingScale::query()->with('rules')->find($exam->grading_scale_id);

            if ($scale) {
                return $scale;
            }
        }

        $default = GradingScale::query()->with('rules')->where('is_default', true)->first();

        if (! $default) {
            throw ValidationException::withMessages([
                'grading_scale_id' => 'No grading scale is assigned to this exam and no default scale exists.',
            ]);
        }

        return $default;
    }

    /**
     * @param  Collection<int, GradingScaleRule>  $rules
     * @return array{letter_grade: string, grade_point: string}
     */
    protected function resolveGrade(Collection $rules, ?float $percent, bool $isAbsent): array
    {
        if ($isAbsent || $percent === null) {
            return ['letter_grade' => 'F', 'grade_point' => '0.00'];
        }

        foreach ($rules as $rule) {
            if ($percent + 1e-9 >= (float) $rule->min_percent && $percent <= (float) $rule->max_percent + 1e-9) {
                return [
                    'letter_grade' => $rule->letter_grade,
                    'grade_point' => number_format((float) $rule->grade_point, 2, '.', ''),
                ];
            }
        }

        return ['letter_grade' => 'F', 'grade_point' => '0.00'];
    }

    /**
     * @param  list<float>  $points
     */
    protected function averageGradePoint(array $points): string
    {
        if ($points === []) {
            return '0.00';
        }

        return number_format(array_sum($points) / count($points), 2, '.', '');
    }

    protected function percentage(string $obtained, float|string $full): float
    {
        if (bccomp((string) $full, '0', 2) <= 0) {
            return 0.0;
        }

        return (float) bcmul(bcdiv($obtained, (string) $full, 6), '100', 4);
    }

    /**
     * Competition ranking with ties: 1,2,2,4
     *
     * @param  list<array{id: int, total_marks: float, status: string}>  $rows
     */
    protected function assignRanks(array $rows): void
    {
        $eligible = collect($rows)
            ->filter(fn (array $row) => $row['status'] !== ExamResultStatus::Absent->value)
            ->sortByDesc('total_marks')
            ->values();

        $rank = 0;
        $index = 0;
        $previousTotal = null;

        foreach ($eligible as $row) {
            $index++;

            if ($previousTotal === null || abs($previousTotal - $row['total_marks']) > 0.0001) {
                $rank = $index;
                $previousTotal = $row['total_marks'];
            }

            ExamResult::withoutGlobalScopes()
                ->whereKey($row['id'])
                ->update(['rank_in_batch' => $rank]);
        }
    }

    protected function notifyPublished(Exam $exam): void
    {
        if (! (bool) $this->settings->get('academic.notify_on_result_publish', false)) {
            return;
        }

        $results = ExamResult::withoutGlobalScopes()
            ->with(['student.user', 'student.guardians'])
            ->where('exam_id', $exam->id)
            ->whereNotNull('published_at')
            ->get();

        foreach ($results as $result) {
            $notifiables = collect();

            if ($result->student?->user) {
                $notifiables->push($result->student->user);
            }

            $notifiables = $notifiables->merge($result->student?->guardians ?? []);

            if ($notifiables->isNotEmpty()) {
                Notification::send($notifiables, new ExamResultPublishedNotification($result));
            }
        }
    }
}
