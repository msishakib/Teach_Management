<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MenuLocation: string implements HasLabel
{
    case Header = 'header';
    case Footer = 'footer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Header => 'Header (top menu + mobile menu)',
            self::Footer => 'Footer ("Explore" column)',
        };
    }
}
