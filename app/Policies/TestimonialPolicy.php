<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermissions;

class TestimonialPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'website';
    }
}