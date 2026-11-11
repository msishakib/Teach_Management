<?php

namespace App\Models\Scopes;

use App\Support\PortalAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class PortalTeacherOwnedScope implements Scope
{
    public function __construct(
        protected string $teacherIdColumn = 'teacher_id',
    ) {}

    public function apply(Builder $builder, Model $model): void
    {
        if (PortalAccess::isStaff()) {
            return;
        }

        $teacherId = PortalAccess::teacherId();

        if ($teacherId === null) {
            if (PortalAccess::isTeacher()) {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        $builder->where($model->getTable().'.'.$this->teacherIdColumn, $teacherId);
    }
}
