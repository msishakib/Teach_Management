<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class ExamSubjectPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'exam';
    }
}
