<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Policies\Concerns\ChecksModulePermissions;
use App\Support\PermissionName;

class LeaveRequestPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'leave_request';
    }

    public function approve(User $user, ?LeaveRequest $model = null): bool
    {
        return $user->can(PermissionName::make('leave_request', 'approve'))
            || $user->can(PermissionName::make('attendance', 'approve'));
    }
}
