<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StudentAttendanceStatus: string implements HasColor, HasLabel
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Late => 'Late',
            self::Excused => 'Excused',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'danger',
            self::Late => 'warning',
            self::Excused => 'info',
        };
    }

    /**
     * @return list<string>
     */
    public static function attendedValues(): array
    {
        return [
            self::Present->value,
            self::Late->value,
            self::Excused->value,
        ];
    }
}
