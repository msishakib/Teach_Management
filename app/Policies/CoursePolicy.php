<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class CoursePolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'course';
    }
}
