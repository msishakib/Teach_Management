<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class UserPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'user';
    }
}
