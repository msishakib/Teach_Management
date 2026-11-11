<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class TeacherProfilePolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'website';
    }
}