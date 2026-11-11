<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\User;
use App\Policies\Concerns\ChecksModulePermissions;
use App\Support\PermissionName;

class ExamMarkPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'exam';
    }

    public function override(User $user, ExamMark|Exam|null $model = null): bool
    {
        return $user->can(PermissionName::make('exam', 'override'));
    }
}
