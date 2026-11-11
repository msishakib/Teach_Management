<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExamResultStatus: string implements HasColor, HasLabel
{
    case Pass = 'pass';
    case Fail = 'fail';
    case Absent = 'absent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pass => 'Pass',
            self::Fail => 'Fail',
            self::Absent => 'Absent',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pass => 'success',
            self::Fail => 'danger',
            self::Absent => 'gray',
        };
    }
}
