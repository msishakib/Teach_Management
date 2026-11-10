<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class AccountPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'account';
    }
}
