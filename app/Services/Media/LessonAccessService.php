<?php

namespace App\Services\Media;

use App\Models\Lesson;
use App\Models\User;
use App\Services\Lms\CourseAccessService;

/**
 * Backwards-compatible wrapper for the media module. All access decisions now
 * live in {@see CourseAccessService} so the LMS and media routes stay aligned.
 */
class LessonAccessService
{
    public function __construct(
        protected CourseAccessService $access,
    ) {}

    public function canView(?User $user, Lesson $lesson): bool
    {
        return $this->access->canView($user, $lesson);
    }
}
