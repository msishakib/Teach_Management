<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Support\PermissionName;
use Illuminate\Database\Eloquent\Model;

trait ChecksModulePermissions
{
    abstract protected function module(): string;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allows($user, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, 'delete');
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->allows($user, 'update');
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->allows($user, 'delete');
    }

    public function export(User $user): bool
    {
        return $this->allows($user, 'export');
    }

    public function approve(User $user, ?Model $model = null): bool
    {
        return $this->allows($user, 'approve');
    }

    protected function allows(User $user, string $action): bool
    {
        return $user->can(PermissionName::make($this->module(), $action));
    }
}
