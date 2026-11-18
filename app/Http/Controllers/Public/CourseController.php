<?php

namespace App\Http\Controllers\Public;

use App\Enums\MediaCategory;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\TeacherProfile;
use App\Services\Lms\CourseAccessService;
use App\Services\Lms\CourseCurriculumService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __invoke(
        Request $request,
        Course $course,
        CourseCurriculumService $curriculum,
        CourseAccessService $access,
    ): View {
        abort_unless($course->is_active && $course->is_published, 404);

        $course->loadMissing(['profile', 'media']);

        $stats = $course->curriculumStats();

        return view('site.courses.show', [
            'course' => $course,
            'curriculum' => $curriculum->publicCurriculum($course),
            'stats' => $stats,
            'instructor' => $this->instructorFor($course),
            'isEnrolled' => $access->canAccessCourse($request->user(), $course),
            'thumbnail' => $course->mediaFor(MediaCategory::CourseThumbnail),
        ]);
    }

    protected function instructorFor(Course $course): ?TeacherProfile
    {
        return TeacherProfile::query()
            ->whereHas('teacher.classRoutines.batch', fn ($query) => $query->where('course_id', $course->id))
            ->with('teacher.media')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->first()
            ?? TeacherProfile::query()
                ->where('is_featured', true)
                ->whereHas('teacher')
                ->with('teacher.media')
                ->orderBy('sort_order')
                ->first();
    }
}
