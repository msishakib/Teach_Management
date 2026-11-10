<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReceiptStatus: string implements HasColor, HasLabel
{
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Posted => 'Posted',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Posted => 'success',
            self::Cancelled => 'gray',
        };
    }
}
