<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class MenuItemPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'website';
    }
}
