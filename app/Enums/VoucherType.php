<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VoucherType: string implements HasColor, HasLabel
{
    case Receipt = 'receipt';
    case Payment = 'payment';
    case Journal = 'journal';
    case Opening = 'opening';

    public function getLabel(): string
    {
        return match ($this) {
            self::Receipt => 'Receipt',
            self::Payment => 'Payment',
            self::Journal => 'Journal',
            self::Opening => 'Opening',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Receipt => 'success',
            self::Payment => 'danger',
            self::Journal => 'info',
            self::Opening => 'gray',
        };
    }

    public function prefixSettingKey(): string
    {
        return match ($this) {
            self::Receipt => 'finance.receipt_prefix',
            self::Payment => 'finance.payment_prefix',
            self::Journal => 'finance.journal_prefix',
            self::Opening => 'finance.opening_prefix',
        };
    }

    public function defaultPrefix(): string
    {
        return match ($this) {
            self::Receipt => 'RCPT',
            self::Payment => 'PAY',
            self::Journal => 'JV',
            self::Opening => 'OPN',
        };
    }
}
