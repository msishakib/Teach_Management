<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EnrollmentStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Completed = 'completed';
    case Dropped = 'dropped';
    case Transferred = 'transferred';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Dropped => 'Dropped',
            self::Transferred => 'Transferred',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::Completed => 'primary',
            self::Dropped => 'danger',
            self::Transferred => 'warning',
        };
    }
}
