<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JournalEntryStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Posted',
            self::Reversed => 'Reversed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Posted => 'success',
            self::Reversed => 'warning',
        };
    }
}
