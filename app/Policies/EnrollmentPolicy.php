<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class EnrollmentPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'enrollment';
    }
}
