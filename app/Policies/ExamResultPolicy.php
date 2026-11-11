<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\ExamResult;
use App\Models\User;
use App\Policies\Concerns\ChecksModulePermissions;
use App\Support\PermissionName;

class ExamResultPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'exam';
    }

    public function view(User $user, ExamResult $result): bool
    {
        if ($user->can(PermissionName::make('exam', 'view')) && $this->isStaff($user)) {
            return true;
        }

        if (! $result->isPublished()) {
            return false;
        }

        if ($user->student && (int) $user->student->id === (int) $result->student_id) {
            return true;
        }

        if ($user->hasRole(RoleName::Guardian->value)) {
            return $result->student()
                ->whereHas('guardians', fn ($q) => $q->where('guardians.user_id', $user->id))
                ->exists();
        }

        return false;
    }

    protected function isStaff(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
            RoleName::Accountant->value,
            RoleName::Teacher->value,
        ]);
    }
}
