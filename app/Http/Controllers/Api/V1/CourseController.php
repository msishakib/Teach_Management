<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\CourseResource;
use App\Models\Batch;
use App\Models\Course;
use App\Models\CourseCompletion;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Support\PortalAccess;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $studentIds = PortalAccess::accessibleStudentIds($user) ?? [];

        if ($studentIds === []) {
            return CourseResource::collection(new LengthAwarePaginator([], 0, 20));
        }

        $enrollments = Enrollment::query()
            ->withoutGlobalScope(PortalStudentOwnedScope::class)
            ->whereIn('student_id', $studentIds)
            ->where('status', 'active')
            ->get(['batch_id', 'student_id', 'enrolled_on'])
            ->keyBy('batch_id');

        $courseIds = $enrollments->isEmpty()
            ? collect()
            : Batch::query()
                ->whereIn('id', $enrollments->keys())
                ->pluck('course_id', 'id');

        $enrolledCourseIds = $enrollments
            ->map(fn ($enrollment) => $courseIds->get($enrollment->batch_id))
            ->filter()
            ->unique()
            ->values();

        if ($enrolledCourseIds->isEmpty()) {
            return CourseResource::collection(new LengthAwarePaginator([], 0, 20));
        }

        $courses = Course::query()
            ->whereIn('id', $enrolledCourseIds)
            ->withCount(['lessons' => fn ($query) => $query->where('is_published', true)])
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->paginate(20);

        $completedLessonCounts = LessonProgress::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('course_id', $enrolledCourseIds)
            ->whereNotNull('completed_at')
            ->selectRaw('course_id, COUNT(*) as total')
            ->groupBy('course_id')
            ->pluck('total', 'course_id');

        $completedCourseIds = CourseCompletion::query()
            ->withoutGlobalScope(PortalStudentOwnedScope::class)
            ->whereIn('student_id', $studentIds)
            ->whereIn('course_id', $enrolledCourseIds)
            ->pluck('course_id')
            ->all();

        $courses->getCollection()->transform(function (Course $course) use ($completedLessonCounts, $completedCourseIds) {
            $course->completed_lessons_count = (int) ($completedLessonCounts->get($course->id) ?? 0);
            $course->is_completed = in_array($course->id, $completedCourseIds, true);

            return $course;
        });

        return CourseResource::collection($courses);
    }
}
