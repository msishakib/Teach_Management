<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class GradingScalePolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'exam';
    }
}
