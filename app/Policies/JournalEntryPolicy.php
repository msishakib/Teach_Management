<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;
use App\Policies\Concerns\ChecksModulePermissions;

class JournalEntryPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'journal';
    }

    public function update(User $user, $model): bool
    {
        if (! $model instanceof JournalEntry || ! $model->isDraft()) {
            return false;
        }

        return $this->allows($user, 'update');
    }

    public function delete(User $user, $model): bool
    {
        if (! $model instanceof JournalEntry || ! $model->isDraft()) {
            return false;
        }

        return $this->allows($user, 'delete');
    }
}
