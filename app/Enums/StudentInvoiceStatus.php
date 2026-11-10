<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StudentInvoiceStatus: string implements HasColor, HasLabel
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Partial => 'Partial',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Unpaid => 'danger',
            self::Partial => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Unpaid, self::Partial], true);
    }
}
