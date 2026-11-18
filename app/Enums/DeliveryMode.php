<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeliveryMode: string implements HasColor, HasLabel
{
    case Offline = 'offline';
    case Online = 'online';
    case Hybrid = 'hybrid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Offline => 'Offline (classroom)',
            self::Online => 'Online',
            self::Hybrid => 'Hybrid',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Offline => 'gray',
            self::Online => 'success',
            self::Hybrid => 'info',
        };
    }

    public function hasOnlineComponent(): bool
    {
        return $this !== self::Offline;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $mode): array => [$mode->value => $mode->getLabel()])
            ->all();
    }
}
