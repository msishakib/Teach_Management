<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class ClassRoutinePolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'class_routine';
    }
}
