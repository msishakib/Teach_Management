<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class BannerPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'website';
    }
}