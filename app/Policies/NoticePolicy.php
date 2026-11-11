<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class NoticePolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'report';
    }
}
