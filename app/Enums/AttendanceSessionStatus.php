<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttendanceSessionStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Finalized = 'finalized';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Finalized => 'Finalized',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Open => 'warning',
            self::Finalized => 'success',
        };
    }
}
