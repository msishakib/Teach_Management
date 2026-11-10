<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TeacherAttendanceStatus: string implements HasColor, HasLabel
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Leave = 'leave';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Late => 'Late',
            self::Leave => 'Leave',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'danger',
            self::Late => 'warning',
            self::Leave => 'info',
        };
    }
}
