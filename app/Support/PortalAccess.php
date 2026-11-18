<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class PortalAccess
{
    public static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public static function isStaff(?User $user = null): bool
    {
        $user ??= self::user();

        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
            RoleName::Accountant->value,
        ]);
    }

    /**
     * Public-site CTA: which Filament panel home URL the signed-in user should open.
     */
    public static function panelHomeUrl(?User $user = null): ?string
    {
        $user ??= self::user();

        if (! $user) {
            return null;
        }

        if (self::isStaff($user) || $user->type === UserType::Admin) {
            return url('/admin');
        }

        if (self::isTeacher($user) || $user->type === UserType::Teacher) {
            return url('/teacher');
        }

        return url('/student');
    }

    public static function isTeacher(?User $user = null): bool
    {
        $user ??= self::user();

        return (bool) ($user?->teacher || $user?->hasRole(RoleName::Teacher->value));
    }

    public static function isStudentOrGuardian(?User $user = null): bool
    {
        $user ??= self::user();

        if (! $user) {
            return false;
        }

        return (bool) (
            $user->student
            || $user->guardian
            || $user->hasRole(RoleName::Student->value)
            || $user->hasRole(RoleName::Guardian->value)
        );
    }

    /**
     * Student IDs the current portal user may access.
     * null = unrestricted (staff / not a portal learner).
     *
     * @return list<int>|null
     */
    public static function accessibleStudentIds(?User $user = null): ?array
    {
        $user ??= self::user();

        if (! $user || self::isStaff($user)) {
            return null;
        }

        if ($user->student) {
            return [(int) $user->student->id];
        }

        if ($user->guardian) {
            return $user->guardian->students()
                ->pluck('students.id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        if ($user->hasRole(RoleName::Student->value) || $user->hasRole(RoleName::Guardian->value)) {
            return [];
        }

        return null;
    }


    public static function teacherId(?User $user = null): ?int
    {
        $user ??= self::user();

        if (! $user || self::isStaff($user)) {
            return null;
        }

        return $user->teacher?->id;
    }

    public static function canAccessStudent(int $studentId, ?User $user = null): bool
    {
        $ids = self::accessibleStudentIds($user);

        if ($ids === null) {
            return true;
        }

        return in_array($studentId, $ids, true);
    }
}
