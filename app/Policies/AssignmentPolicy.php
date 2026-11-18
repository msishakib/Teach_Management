<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;
use App\Policies\Concerns\ChecksModulePermissions;
use App\Services\Lms\CourseAccessService;

class AssignmentPolicy
{
    use ChecksModulePermissions;

    public function __construct(
        protected CourseAccessService $access,
    ) {}

    protected function module(): string
    {
        return 'assignment';
    }

    public function submit(User $user, Assignment $assignment): bool
    {
        return $this->access->canAccessCourse($user, (int) $assignment->course_id);
    }

    public function grade(User $user, Assignment $assignment): bool
    {
        return $this->allows($user, 'approve') || $this->allows($user, 'update');
    }
}
