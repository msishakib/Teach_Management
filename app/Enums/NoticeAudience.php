<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NoticeAudience: string implements HasLabel
{
    case All = 'all';
    case Students = 'students';
    case Teachers = 'teachers';
    case Guardians = 'guardians';
    case Batch = 'batch';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'Everyone',
            self::Students => 'Students & guardians',
            self::Teachers => 'Teachers',
            self::Guardians => 'Guardians only',
            self::Batch => 'Specific batch',
        };
    }
}
