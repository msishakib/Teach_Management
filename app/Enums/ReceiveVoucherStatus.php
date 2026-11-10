<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReceiveVoucherStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Posted = 'posted';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Posted => 'Posted',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Approved => 'warning',
            self::Posted => 'success',
        };
    }
}
