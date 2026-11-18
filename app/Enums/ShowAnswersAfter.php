<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ShowAnswersAfter: string implements HasLabel
{
    case Never = 'never';
    case AfterSubmit = 'after_submit';
    case AfterPass = 'after_pass';

    public function getLabel(): string
    {
        return match ($this) {
            self::Never => 'Never',
            self::AfterSubmit => 'After submit',
            self::AfterPass => 'After pass',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->getLabel()])
            ->all();
    }
}
