<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class TeacherPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'teacher';
    }
}
