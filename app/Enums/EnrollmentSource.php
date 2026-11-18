<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EnrollmentSource: string implements HasLabel
{
    case Offline = 'offline';
    case Online = 'online';

    public function getLabel(): string
    {
        return match ($this) {
            self::Offline => 'Offline (office)',
            self::Online => 'Online',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $source): array => [$source->value => $source->getLabel()])
            ->all();
    }
}
