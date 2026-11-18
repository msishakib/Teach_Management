<?php

namespace App\Policies;

use App\Models\AssignmentSubmission;
use App\Models\Media;
use App\Models\User;
use App\Services\Lms\CourseAccessService;
use App\Support\PortalAccess;

class AssignmentSubmissionPolicy
{
    public function __construct(
        protected CourseAccessService $access,
    ) {}

    public function view(User $user, AssignmentSubmission $submission): bool
    {
        if ($this->access->isStaff($user) || $user->can('assignment.view')) {
            if ($this->access->isStaff($user) || $user->can('assignment.update') || $user->can('assignment.approve')) {
                return true;
            }
        }

        return PortalAccess::canAccessStudent((int) $submission->student_id, $user);
    }

    public function download(User $user, AssignmentSubmission $submission): bool
    {
        return $this->view($user, $submission);
    }

    public function update(User $user, AssignmentSubmission $submission): bool
    {
        if ($user->can('assignment.approve') || $user->can('assignment.update')) {
            return true;
        }

        return PortalAccess::canAccessStudent((int) $submission->student_id, $user)
            && $submission->status->value === 'submitted';
    }

    public function viewFile(User $user, AssignmentSubmission $submission, Media $media): bool
    {
        if ((int) $media->model_id !== (int) $submission->getKey()
            || $media->model_type !== $submission->getMorphClass()) {
            return false;
        }

        return $this->download($user, $submission);
    }
}
