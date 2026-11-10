<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Support\PermissionName;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionCatalogService
{
    /**
     * @return list<string>
     */
    public function allPermissionNames(): array
    {
        $names = [];

        foreach (config('permission-matrix.modules') as $module) {
            foreach (config('permission-matrix.actions') as $action) {
                $names[] = PermissionName::make($module, $action);
            }
        }

        foreach (config('permission-matrix.extra', []) as $permission) {
            $names[] = $permission;
        }

        return array_values(array_unique($names));
    }

    /**
     * Seed every {module}.{action} permission for the configured guard.
     *
     * @return Collection<int, Permission>
     */
    public function syncPermissions(): Collection
    {
        $guard = config('permission-matrix.guard', 'web');
        $permissions = collect();

        foreach ($this->allPermissionNames() as $name) {
            $permissions->push(
                Permission::findOrCreate($name, $guard)
            );
        }

        return $permissions;
    }

    /**
     * Ensure default roles exist and sync their permission matrix.
     *
     * @return Collection<int, Role>
     */
    public function syncRolesAndMatrix(): Collection
    {
        $guard = config('permission-matrix.guard', 'web');
        $matrix = config('permission-matrix.matrix', []);
        $roles = collect();

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, $guard);
            $roles->push($role);

            if ($roleName === RoleName::SuperAdmin) {
                // Bypass via Gate::before — no explicit permission grants required.
                continue;
            }

            $role->syncPermissions(
                $this->resolvePermissionsForRole($roleName->value, $matrix[$roleName->value] ?? [])
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $roles;
    }

    /**
     * @param  array<string, list<string>>  $moduleActions
     * @return list<string>
     */
    public function resolvePermissionsForRole(string $role, array $moduleActions): array
    {
        if (array_key_exists('*', $moduleActions) && in_array('*', $moduleActions['*'], true)) {
            return $this->allPermissionNames();
        }

        $permissions = [];
        $actions = config('permission-matrix.actions');

        foreach ($moduleActions as $module => $moduleActionList) {
            $resolvedActions = in_array('*', $moduleActionList, true)
                ? $actions
                : $moduleActionList;

            foreach ($resolvedActions as $action) {
                $permissions[] = PermissionName::make($module, $action);
            }
        }

        return $permissions;
    }

    /**
     * Group stored permissions by module for Filament checkbox UI.
     *
     * @return array<string, array<string, string>>
     */
    public function groupedPermissionOptions(?string $guard = null): array
    {
        $guard ??= config('permission-matrix.guard', 'web');

        return Permission::query()
            ->where('guard_name', $guard)
            ->where('name', 'like', '%.%')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => PermissionName::module($permission->name))
            ->mapWithKeys(function (Collection $permissions, string $module) {
                return [
                    $module => $permissions
                        ->mapWithKeys(fn (Permission $permission) => [
                            $permission->name => PermissionName::action($permission->name),
                        ])
                        ->all(),
                ];
            })
            ->all();
    }
}
