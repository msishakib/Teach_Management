<?php

namespace App\Policies;

use App\Models\LessonResource;
use App\Models\User;
use App\Services\Lms\CourseAccessService;

class LessonResourcePolicy
{
    public function __construct(
        protected CourseAccessService $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('course.view');
    }

    public function view(?User $user, LessonResource $resource): bool
    {
        return $this->access->canViewResource($user, $resource);
    }

    public function download(?User $user, LessonResource $resource): bool
    {
        return $this->access->canViewResource($user, $resource);
    }

    public function create(User $user): bool
    {
        return $user->can('course.update');
    }

    public function update(User $user, LessonResource $resource): bool
    {
        return $user->can('course.update');
    }

    public function delete(User $user, LessonResource $resource): bool
    {
        return $user->can('course.delete');
    }
}
