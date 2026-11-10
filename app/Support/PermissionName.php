<?php

namespace App\Support;

final class PermissionName
{
    public static function make(string $module, string $action): string
    {
        return strtolower($module).'.'.strtolower($action);
    }

    public static function module(string $permission): string
    {
        return str($permission)->before('.')->toString();
    }

    public static function action(string $permission): string
    {
        return str($permission)->after('.')->toString();
    }
}
