<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Policies\Concerns\ChecksModulePermissions;
use App\Support\PortalAccess;
use Illuminate\Database\Eloquent\Model;

class OrderPolicy
{
    use ChecksModulePermissions;

    protected function module(): string
    {
        return 'payment';
    }

    public function view(User $user, Model $model): bool
    {
        /** @var Order $model */
        if ($this->allows($user, 'view')) {
            return true;
        }

        if (PortalAccess::isStudentOrGuardian()) {
            $ids = PortalAccess::accessibleStudentIds() ?? [];

            return in_array((int) $model->student_id, array_map('intval', $ids), true);
        }

        return (int) $model->user_id === (int) $user->id;
    }
}
