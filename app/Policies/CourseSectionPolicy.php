<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class CourseSectionPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'course';
    }
}
