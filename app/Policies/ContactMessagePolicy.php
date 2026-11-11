<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class ContactMessagePolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'website';
    }
}