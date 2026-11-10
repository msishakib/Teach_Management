<?php

namespace App\Policies;

use App\Models\AttendanceSession;
use App\Models\Batch;
use App\Models\User;
use App\Policies\Concerns\ChecksModulePermissions;
use App\Services\AttendanceService;
use App\Support\PermissionName;

class AttendanceSessionPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'attendance';
    }

    public function take(User $user, Batch $batch): bool
    {
        if (! $user->can(PermissionName::make('attendance', 'create'))
            && ! $user->can(PermissionName::make('attendance', 'update'))) {
            return false;
        }

        return app(AttendanceService::class)->teacherCanAccessBatch($user, $batch);
    }

    public function update(User $user, AttendanceSession $session): bool
    {
        if (! $this->allows($user, 'update') && ! $user->can(PermissionName::make('attendance', 'override'))) {
            return false;
        }

        if ($session->isFinalized() && ! $user->can(PermissionName::make('attendance', 'override'))) {
            return false;
        }

        return app(AttendanceService::class)->teacherCanAccessBatch($user, $session->batch);
    }

    public function finalize(User $user, AttendanceSession $session): bool
    {
        return $this->update($user, $session);
    }

    public function override(User $user, ?AttendanceSession $session = null): bool
    {
        return $user->can(PermissionName::make('attendance', 'override'));
    }
}
