<?php

namespace App\Policies;

use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Lms\CourseAccessService;
use App\Support\PortalAccess;

class QuizAttemptPolicy
{
    public function __construct(
        protected CourseAccessService $access,
    ) {}

    public function view(User $user, QuizAttempt $attempt): bool
    {
        if ($this->access->isStaff($user)) {
            return true;
        }

        return PortalAccess::canAccessStudent((int) $attempt->student_id, $user);
    }

    public function update(User $user, QuizAttempt $attempt): bool
    {
        return $this->view($user, $attempt);
    }
}
