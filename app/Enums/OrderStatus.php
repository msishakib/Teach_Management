<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'gray',
            self::Refunded => 'info',
        };
    }
}
