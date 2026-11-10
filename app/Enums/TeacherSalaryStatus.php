<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TeacherSalaryStatus: string implements HasColor, HasLabel
{
    case Generated = 'generated';
    case VoucherCreated = 'voucher_created';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Generated => 'Generated',
            self::VoucherCreated => 'Voucher created',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Generated => 'info',
            self::VoucherCreated => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function canPay(): bool
    {
        return $this === self::Generated;
    }
}
