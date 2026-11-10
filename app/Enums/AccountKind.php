<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AccountKind: string implements HasLabel
{
    case Group = 'group';
    case SubGroup = 'sub_group';
    case Ledger = 'ledger';

    public function getLabel(): string
    {
        return match ($this) {
            self::Group => 'Group',
            self::SubGroup => 'Sub group',
            self::Ledger => 'Ledger',
        };
    }

    public function isGroup(): bool
    {
        return $this !== self::Ledger;
    }
}
