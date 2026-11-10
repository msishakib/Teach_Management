<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AccountType: string implements HasColor, HasLabel
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    public function getLabel(): string
    {
        return match ($this) {
            self::Asset => 'Asset',
            self::Liability => 'Liability',
            self::Equity => 'Equity',
            self::Income => 'Income',
            self::Expense => 'Expense',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Asset => 'info',
            self::Liability => 'warning',
            self::Equity => 'primary',
            self::Income => 'success',
            self::Expense => 'danger',
        };
    }
}
