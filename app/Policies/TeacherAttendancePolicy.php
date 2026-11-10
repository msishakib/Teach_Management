<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class TeacherAttendancePolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'attendance';
    }
}
