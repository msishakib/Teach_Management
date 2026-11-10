<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class RolePolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'role';
    }
}
