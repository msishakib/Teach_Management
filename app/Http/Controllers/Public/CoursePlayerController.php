<?php

namespace App\Http\Controllers\Public;

use App\Enums\LessonType;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Services\Lms\CourseAccessService;
use App\Services\Lms\CourseCompletionService;
use App\Services\Lms\CourseCurriculumService;
use App\Services\Lms\LessonPlaybackService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CoursePlayerController extends Controller
{
    public function __invoke(
        Request $request,
        Course $course,
        ?Lesson $lesson,
        CourseCurriculumService $curriculumService,
        CourseAccessService $access,
        LessonPlaybackService $playback,
        CourseCompletionService $completions,
    ): View|RedirectResponse {
        abort_unless($course->is_active && $course->is_published, 404);

        $user = $request->user();

        abort_unless($access->canAccessCourse($user, $course), 403);

        $curriculum = $curriculumService->publicCurriculum($course);
        $flat = $curriculumService->flatten($curriculum);

        if ($lesson === null) {
            $first = $flat[0] ?? null;

            abort_if($first === null, 404, 'This course has no published lessons yet.');

            return redirect()->route('courses.learn', [
                'course' => $course->code,
                'lesson' => $first['id'],
            ]);
        }

        abort_unless((int) $lesson->course_id === (int) $course->id, 404);
        abort_unless($lesson->isPublished(), 404);
        abort_unless($access->canView($user, $lesson), 403);

        $lesson->loadMissing(['resources.fileMedia', 'resources.media', 'primaryMedia', 'section']);

        $index = collect($flat)->search(fn (array $item): bool => (int) $item['id'] === (int) $lesson->getKey());

        $dripDays = collect($flat)
            ->mapWithKeys(fn (array $item): array => [(int) $item['id'] => $item['drip_after_days']])
            ->all();

        $quiz = $lesson->type === LessonType::Quiz
            ? Quiz::query()->where('course_id', $course->id)->where('lesson_id', $lesson->id)->first()
            : null;

        $assignment = $lesson->type === LessonType::Assignment
            ? Assignment::query()->where('course_id', $course->id)->where('lesson_id', $lesson->id)->first()
            : null;

        $courseComplete = $user?->student
            ? $completions->isComplete($user->student, $course)
            : false;

        return view('site.courses.player', [
            'course' => $course,
            'lesson' => $lesson,
            'curriculum' => $curriculum,
            'video' => $playback->payload($user, $lesson),
            'previous' => $index !== false && $index > 0 ? $flat[$index - 1] : null,
            'next' => $index !== false && isset($flat[$index + 1]) ? $flat[$index + 1] : null,
            'lessonNumber' => $index !== false ? $index + 1 : null,
            'totalLessons' => count($flat),
            'lockedLessonIds' => $access->dripLockedLessonIds($user, (int) $course->id, $dripDays),
            'quiz' => $quiz,
            'assignment' => $assignment,
            'courseComplete' => $courseComplete,
        ]);
    }
}
