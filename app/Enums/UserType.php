<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserType: string implements HasLabel
{
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Student = 'student';
    case Guardian = 'guardian';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Teacher => 'Teacher',
            self::Student => 'Student',
            self::Guardian => 'Guardian',
        };
    }
}
