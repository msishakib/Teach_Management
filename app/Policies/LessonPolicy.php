<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;
use App\Services\Lms\CourseAccessService;

class LessonPolicy
{
    public function __construct(
        protected CourseAccessService $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('course.view');
    }

    public function view(?User $user, Lesson $lesson): bool
    {
        return $this->access->canView($user, $lesson);
    }

    public function create(User $user): bool
    {
        return $user->can('course.update');
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->can('course.update');
    }

    public function reorder(User $user): bool
    {
        return $user->can('course.update');
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->can('course.delete');
    }
}
