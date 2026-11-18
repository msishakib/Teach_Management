<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UpdateProgressRequest;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Services\Lms\CourseAccessService;
use App\Services\Lms\CourseCompletionService;
use Illuminate\Http\JsonResponse;

class ProgressController extends Controller
{
    public function update(
        UpdateProgressRequest $request,
        CourseAccessService $access,
        CourseCompletionService $completions,
    ): JsonResponse {
        $user = $request->user();
        $student = $user?->student;

        abort_unless($student !== null, 403, 'Only students can update progress.');

        $lesson = Lesson::query()->findOrFail((int) $request->integer('lesson_id'));

        abort_unless($access->canView($user, $lesson), 403, 'You do not have access to this lesson.');

        $completed = $request->boolean('completed', true);

        $progress = LessonProgress::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'course_id' => $lesson->course_id,
                'completed_at' => $completed ? now() : null,
            ],
        );

        $completion = $completions->recalculate($student->id, (int) $lesson->course_id);

        $totalLessons = Lesson::query()
            ->where('course_id', $lesson->course_id)
            ->where('is_published', true)
            ->count();

        $completedLessons = LessonProgress::query()
            ->withoutGlobalScope(PortalStudentOwnedScope::class)
            ->where('student_id', $student->id)
            ->where('course_id', $lesson->course_id)
            ->whereNotNull('completed_at')
            ->count();

        return response()->json([
            'data' => [
                'lesson_id' => $lesson->id,
                'course_id' => $lesson->course_id,
                'completed' => $progress->completed_at !== null,
                'completed_at' => $progress->completed_at?->toISOString(),
                'completed_lessons' => $completedLessons,
                'total_lessons' => $totalLessons,
                'progress_percent' => $totalLessons > 0
                    ? round(($completedLessons / $totalLessons) * 100, 2)
                    : 0,
                'course_completed' => $completion !== null,
                'course_completed_at' => $completion?->completed_at?->toISOString(),
            ],
        ]);
    }
}
