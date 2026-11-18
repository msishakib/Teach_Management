<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class CouponPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'payment';
    }
}
