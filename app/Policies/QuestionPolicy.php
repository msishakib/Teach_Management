<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class QuestionPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'quiz';
    }
}
