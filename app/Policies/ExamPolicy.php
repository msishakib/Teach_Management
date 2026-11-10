<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;
use App\Policies\Concerns\ChecksModulePermissions;
use App\Support\PermissionName;

class ExamPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'exam';
    }

    public function publish(User $user, ?Exam $exam = null): bool
    {
        return $user->can(PermissionName::make('exam', 'publish'));
    }
}
