<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Bkash = 'bkash';
    case Nagad = 'nagad';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank',
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
        };
    }

    public function defaultAccountCode(): ?string
    {
        return match ($this) {
            self::Cash => '1110',
            self::Bank => '1120',
            self::Bkash => '1130',
            self::Nagad => '1135',
        };
    }
}
