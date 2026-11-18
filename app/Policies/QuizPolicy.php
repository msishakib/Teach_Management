<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;
use App\Policies\Concerns\ChecksModulePermissions;
use App\Services\Lms\CourseAccessService;

class QuizPolicy
{
    use ChecksModulePermissions;

    public function __construct(
        protected CourseAccessService $access,
    ) {}

    protected function module(): string
    {
        return 'quiz';
    }

    public function attempt(User $user, Quiz $quiz): bool
    {
        return $this->access->canAccessCourse($user, (int) $quiz->course_id);
    }

    public function viewAnalytics(User $user, Quiz $quiz): bool
    {
        return $this->allows($user, 'view');
    }
}
