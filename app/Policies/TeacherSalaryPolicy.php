<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class TeacherSalaryPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'payment';
    }
}
