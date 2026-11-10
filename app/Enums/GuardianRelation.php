<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GuardianRelation: string implements HasLabel
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Father => 'Father',
            self::Mother => 'Mother',
            self::Guardian => 'Guardian',
            self::Other => 'Other',
        };
    }
}
