<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class FaqPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'website';
    }
}