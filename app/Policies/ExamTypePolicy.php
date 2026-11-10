<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class ExamTypePolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'exam';
    }
}
