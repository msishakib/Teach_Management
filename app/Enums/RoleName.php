<?php

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Teacher = 'teacher';
    case Student = 'student';
    case Guardian = 'guardian';

    /**
     * Roles allowed to access the Filament admin panel.
     *
     * @return list<self>
     */
    public static function adminPanelRoles(): array
    {
        return [
            self::SuperAdmin,
            self::Admin,
            self::Accountant,
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
