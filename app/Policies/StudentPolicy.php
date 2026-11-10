<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class StudentPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'student';
    }
}
