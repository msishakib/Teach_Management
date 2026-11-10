<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AcademicSessionStatus: string implements HasColor, HasLabel
{
    case Upcoming = 'upcoming';
    case Active = 'active';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming',
            self::Active => 'Active',
            self::Closed => 'Closed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Upcoming => 'info',
            self::Active => 'success',
            self::Closed => 'gray',
        };
    }
}
