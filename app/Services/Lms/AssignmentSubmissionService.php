<?php

namespace App\Services\Lms;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\MediaCategory;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Models\Student;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentSubmissionService
{
    public function __construct(
        protected MediaService $media,
        protected CourseCompletionService $completions,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function submit(Assignment $assignment, Student $student, ?string $text, array $files = []): AssignmentSubmission
    {
        if (! $assignment->acceptsSubmissions()) {
            throw ValidationException::withMessages([
                'assignment' => 'Late submissions are not allowed for this assignment.',
            ]);
        }

        if (count($files) > (int) $assignment->max_files) {
            throw ValidationException::withMessages([
                'files' => "You may upload at most {$assignment->max_files} file(s).",
            ]);
        }

        return DB::transaction(function () use ($assignment, $student, $text, $files): AssignmentSubmission {
            $existing = AssignmentSubmission::query()
                ->withoutGlobalScope(PortalStudentOwnedScope::class)
                ->where('assignment_id', $assignment->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status === AssignmentSubmissionStatus::Graded) {
                throw ValidationException::withMessages([
                    'assignment' => 'This submission has already been graded.',
                ]);
            }

            $isLate = $assignment->isPastDue();

            $submission = $existing ?? new AssignmentSubmission([
                'assignment_id' => $assignment->id,
                'student_id' => $student->id,
            ]);

            $submission->fill([
                'text' => $text,
                'status' => AssignmentSubmissionStatus::Submitted,
                'submitted_at' => now(),
                'is_late' => $isLate,
                'marks' => null,
                'feedback' => null,
                'graded_by' => null,
                'graded_at' => null,
            ]);
            $submission->save();

            foreach ($files as $file) {
                $this->media->store($file, $submission, MediaCategory::AssignmentSubmission, $student->user_id);
            }

            if ($submission->files()->count() > (int) $assignment->max_files) {
                throw ValidationException::withMessages([
                    'files' => "You may upload at most {$assignment->max_files} file(s).",
                ]);
            }

            $this->completions->recalculate($student->id, (int) $assignment->course_id);

            return $submission->fresh();
        });
    }

    public function grade(
        AssignmentSubmission $submission,
        User $grader,
        float $marks,
        ?string $feedback = null,
        bool $returnToStudent = false,
    ): AssignmentSubmission {
        return DB::transaction(function () use ($submission, $grader, $marks, $feedback, $returnToStudent): AssignmentSubmission {
            $submission = AssignmentSubmission::query()
                ->withoutGlobalScope(PortalStudentOwnedScope::class)
                ->with('assignment')
                ->lockForUpdate()
                ->findOrFail($submission->getKey());

            $max = (float) $submission->assignment->max_marks;
            $marks = max(0, min($marks, $max));

            $submission->forceFill([
                'marks' => $marks,
                'feedback' => $feedback,
                'graded_by' => $grader->id,
                'graded_at' => now(),
                'status' => $returnToStudent
                    ? AssignmentSubmissionStatus::Returned
                    : AssignmentSubmissionStatus::Graded,
            ])->save();

            $this->completions->recalculate(
                (int) $submission->student_id,
                (int) $submission->assignment->course_id,
            );

            return $submission->fresh(['assignment', 'grader']);
        });
    }
}
