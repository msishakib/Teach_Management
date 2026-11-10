<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class PaymentVoucherPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'payment';
    }
}
