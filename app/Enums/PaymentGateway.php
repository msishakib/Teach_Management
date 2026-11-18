<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentGateway: string implements HasLabel
{
    case SslCommerz = 'sslcommerz';
    case Bkash = 'bkash';
    case Manual = 'manual';
    case Fake = 'fake';

    public function getLabel(): string
    {
        return match ($this) {
            self::SslCommerz => 'SSLCommerz',
            self::Bkash => 'bKash',
            self::Manual => 'Manual (bKash/Nagad)',
            self::Fake => 'Fake (local)',
        };
    }

    public function defaultLedgerCode(): string
    {
        return match ($this) {
            self::SslCommerz => '1120', // Bank
            self::Bkash => '1130',
            self::Manual => '1130',
            self::Fake => '1120',
        };
    }

    public function defaultPaymentMethod(): PaymentMethod
    {
        return match ($this) {
            self::SslCommerz => PaymentMethod::Bank,
            self::Bkash => PaymentMethod::Bkash,
            self::Manual => PaymentMethod::Bkash,
            self::Fake => PaymentMethod::Bank,
        };
    }

    /**
     * @return list<self>
     */
    public static function selectable(): array
    {
        $drivers = [
            self::SslCommerz,
            self::Bkash,
            self::Manual,
        ];

        if (! app()->environment('production')) {
            $drivers[] = self::Fake;
        }

        return $drivers;
    }
}
