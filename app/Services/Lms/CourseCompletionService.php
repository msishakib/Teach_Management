<?php

namespace App\Services\Lms;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\QuizAttemptStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseCompletion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * A course is complete when every quiz is passed (best attempt percent >= pass_percent)
 * and every assignment is graded at >= 60% of max marks.
 */
class CourseCompletionService
{
    public const ASSIGNMENT_PASS_PERCENT = 60.0;

    public function hasPassedQuiz(int $studentId, Quiz $quiz): bool
    {
        $best = QuizAttempt::query()
            ->withoutGlobalScope(PortalStudentOwnedScope::class)
            ->where('quiz_id', $quiz->id)
            ->where('student_id', $studentId)
            ->whereIn('status', [
                QuizAttemptStatus::Submitted->value,
                QuizAttemptStatus::Graded->value,
            ])
            ->max('percent');

        return $best !== null && (float) $best >= (float) $quiz->pass_percent;
    }

    public function hasPassedAssignment(int $studentId, Assignment $assignment): bool
    {
        $submission = AssignmentSubmission::query()
            ->withoutGlobalScope(PortalStudentOwnedScope::class)
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $studentId)
            ->whereIn('status', [
                AssignmentSubmissionStatus::Graded->value,
                AssignmentSubmissionStatus::Returned->value,
            ])
            ->first();

        if (! $submission || $submission->marks === null || (float) $assignment->max_marks <= 0) {
            return false;
        }

        $percent = ((float) $submission->marks / (float) $assignment->max_marks) * 100;

        return $percent >= self::ASSIGNMENT_PASS_PERCENT;
    }

    public function requirementsMet(int $studentId, Course|int $course): bool
    {
        $courseId = $course instanceof Course ? (int) $course->getKey() : $course;

        $quizzes = Quiz::query()
            ->where('course_id', $courseId)
            ->get(['id', 'pass_percent']);

        if ($quizzes->isNotEmpty()) {
            $bestByQuiz = QuizAttempt::query()
                ->withoutGlobalScope(PortalStudentOwnedScope::class)
                ->whereIn('quiz_id', $quizzes->pluck('id'))
                ->where('student_id', $studentId)
                ->whereIn('status', [
                    QuizAttemptStatus::Submitted->value,
                    QuizAttemptStatus::Graded->value,
                ])
                ->groupBy('quiz_id')
                ->selectRaw('quiz_id, MAX(percent) as best_percent')
                ->pluck('best_percent', 'quiz_id');

            foreach ($quizzes as $quiz) {
                $best = $bestByQuiz[$quiz->id] ?? null;

                if ($best === null || (float) $best < (float) $quiz->pass_percent) {
                    return false;
                }
            }
        }

        $assignments = Assignment::query()
            ->where('course_id', $courseId)
            ->get(['id', 'max_marks']);

        if ($assignments->isNotEmpty()) {
            $submissions = AssignmentSubmission::query()
                ->withoutGlobalScope(PortalStudentOwnedScope::class)
                ->whereIn('assignment_id', $assignments->pluck('id'))
                ->where('student_id', $studentId)
                ->whereIn('status', [
                    AssignmentSubmissionStatus::Graded->value,
                    AssignmentSubmissionStatus::Returned->value,
                ])
                ->get(['assignment_id', 'marks'])
                ->groupBy('assignment_id')
                ->map(fn ($rows) => $rows->first());

            foreach ($assignments as $assignment) {
                $submission = $submissions->get($assignment->id);

                if (! $submission || $submission->marks === null || (float) $assignment->max_marks <= 0) {
                    return false;
                }

                $percent = ((float) $submission->marks / (float) $assignment->max_marks) * 100;

                if ($percent < self::ASSIGNMENT_PASS_PERCENT) {
                    return false;
                }
            }
        }

        // No quizzes/assignments means nothing to gate yet — not auto-complete.
        return $quizzes->isNotEmpty() || $assignments->isNotEmpty();
    }

    public function recalculate(int $studentId, int $courseId): ?CourseCompletion
    {
        return DB::transaction(function () use ($studentId, $courseId): ?CourseCompletion {
            if (! $this->requirementsMet($studentId, $courseId)) {
                CourseCompletion::query()
                    ->withoutGlobalScope(PortalStudentOwnedScope::class)
                    ->where('course_id', $courseId)
                    ->where('student_id', $studentId)
                    ->delete();

                return null;
            }

            return CourseCompletion::query()
                ->withoutGlobalScope(PortalStudentOwnedScope::class)
                ->updateOrCreate(
                    [
                        'course_id' => $courseId,
                        'student_id' => $studentId,
                    ],
                    [
                        'completed_at' => now(),
                    ],
                );
        });
    }

    public function isComplete(Student|int $student, Course|int $course): bool
    {
        $studentId = $student instanceof Student ? (int) $student->getKey() : $student;
        $courseId = $course instanceof Course ? (int) $course->getKey() : $course;

        return CourseCompletion::query()
            ->withoutGlobalScope(PortalStudentOwnedScope::class)
            ->where('course_id', $courseId)
            ->where('student_id', $studentId)
            ->exists();
    }
}
