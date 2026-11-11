<?php

namespace App\Models\Scopes;

use App\Support\PortalAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class PortalStudentOwnedScope implements Scope
{
    public function __construct(
        protected string $studentIdColumn = 'student_id',
        protected bool $hideFromTeachers = false,
    ) {}

    public function apply(Builder $builder, Model $model): void
    {
        if (PortalAccess::isStaff()) {
            return;
        }

        if (PortalAccess::isStudentOrGuardian()) {
            $ids = PortalAccess::accessibleStudentIds() ?? [];
            $column = $model->getTable().'.'.$this->studentIdColumn;

            if ($ids === []) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->whereIn($column, $ids);

            return;
        }

        if ($this->hideFromTeachers && PortalAccess::isTeacher()) {
            $builder->whereRaw('1 = 0');
        }
    }
}
