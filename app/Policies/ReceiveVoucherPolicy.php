<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class ReceiveVoucherPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'voucher';
    }
}
