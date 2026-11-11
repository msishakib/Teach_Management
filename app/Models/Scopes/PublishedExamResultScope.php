<?php

namespace App\Models\Scopes;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class PublishedExamResultScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        if ($this->isStaff($user)) {
            return;
        }

        $table = $model->getTable();
        $builder->whereNotNull("{$table}.published_at");

        if ($studentId = $user->student?->id) {
            $builder->where("{$table}.student_id", $studentId);

            return;
        }

        if ($user->hasRole(RoleName::Guardian->value)) {
            $builder->whereIn("{$table}.student_id", function ($query) use ($user) {
                $query->select('guardian_student.student_id')
                    ->from('guardian_student')
                    ->join('guardians', 'guardians.id', '=', 'guardian_student.guardian_id')
                    ->where('guardians.user_id', $user->id);
            });

            return;
        }

        $builder->whereRaw('1 = 0');
    }

    protected function isStaff(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
            RoleName::Accountant->value,
            RoleName::Teacher->value,
        ]) || $user->can('exam.publish') || $user->can('exam.override') || $user->can('exam.update');
    }
}
