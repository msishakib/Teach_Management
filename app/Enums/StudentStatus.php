<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StudentStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Alumni = 'alumni';
    case Suspended = 'suspended';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Alumni => 'Alumni',
            self::Suspended => 'Suspended',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Alumni => 'info',
            self::Suspended => 'danger',
        };
    }
}
