<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class AcademicSessionPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'academic_session';
    }
}
