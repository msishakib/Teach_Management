<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FeeFrequency: string implements HasLabel
{
    case Monthly = 'monthly';
    case OneTime = 'one_time';
    case Yearly = 'yearly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::OneTime => 'One Time',
            self::Yearly => 'Yearly',
        };
    }
}
