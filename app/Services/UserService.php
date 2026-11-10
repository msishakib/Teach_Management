<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserService
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string|int>  $roleIds
     */
    public function create(array $attributes, array $roleIds = []): User
    {
        return DB::transaction(function () use ($attributes, $roleIds) {
            $roles = $this->resolveRoles($roleIds);

            $user = User::query()->create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'type' => $attributes['type'] instanceof UserType
                    ? $attributes['type']
                    : UserType::from((string) $attributes['type']),
                'email_verified_at' => now(),
            ]);

            if ($roles !== []) {
                $user->syncRoles($roles);
            }

            return $user->fresh(['roles']) ?? $user;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string|int>  $roleIds
     */
    public function update(User $user, array $attributes, array $roleIds = []): User
    {
        return DB::transaction(function () use ($user, $attributes, $roleIds) {
            $roles = $this->resolveRoles($roleIds);

            $payload = [
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'type' => $attributes['type'] instanceof UserType
                    ? $attributes['type']
                    : UserType::from((string) $attributes['type']),
            ];

            if (filled($attributes['password'] ?? null)) {
                $payload['password'] = $attributes['password'];
            }

            $user->update($payload);
            $user->syncRoles($roles);

            return $user->fresh(['roles']) ?? $user;
        });
    }

    public function delete(User $user, ?User $actor = null): void
    {
        if ($actor && $actor->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        if (
            $user->hasRole(RoleName::SuperAdmin->value)
            && User::role(RoleName::SuperAdmin->value)->count() <= 1
        ) {
            throw ValidationException::withMessages([
                'user' => 'Cannot delete the last super admin.',
            ]);
        }

        $user->delete();
    }

    /**
     * @param  list<string|int>  $roleIds
     * @return list<Role>
     */
    protected function resolveRoles(array $roleIds): array
    {
        $ids = array_values(array_filter($roleIds, static fn ($id) => filled($id)));

        if ($ids === []) {
            return [];
        }

        return Role::query()
            ->where('guard_name', config('permission-matrix.guard', 'web'))
            ->whereIn('id', $ids)
            ->get()
            ->all();
    }
}
