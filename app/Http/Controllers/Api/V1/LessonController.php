<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\LessonResource as LessonApiResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Services\Lms\CourseAccessService;
use App\Support\PortalAccess;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    public function index(Request $request, CourseAccessService $access)
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
        ]);

        $course = Course::query()->findOrFail((int) $data['course_id']);
        $user = $request->user();

        abort_unless($access->canAccessCourse($user, $course), 403, 'You do not have access to this course.');

        $lessons = Lesson::query()
            ->where('course_id', $course->id)
            ->where('is_published', true)
            ->when($data['section_id'] ?? null, fn ($query, $sectionId) => $query->where('section_id', $sectionId))
            ->with('resources')
            ->ordered()
            ->paginate(50);

        $studentIds = PortalAccess::accessibleStudentIds($user) ?? [];
        $completedLessonIds = $studentIds === []
            ? collect()
            : LessonProgress::query()
                ->whereIn('student_id', $studentIds)
                ->where('course_id', $course->id)
                ->whereNotNull('completed_at')
                ->pluck('lesson_id')
                ->all();

        $lessons->getCollection()->transform(function (Lesson $lesson) use ($access, $user, $completedLessonIds) {
            $lesson->is_completed = in_array($lesson->id, $completedLessonIds, true);
            $lesson->is_locked = ! $access->canView($user, $lesson);

            return $lesson;
        });

        return LessonApiResource::collection($lessons);
    }
}
