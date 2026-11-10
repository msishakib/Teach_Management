<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class GuardianPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'guardian';
    }
}
