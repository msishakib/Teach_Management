<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class SubjectPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'subject';
    }
}
