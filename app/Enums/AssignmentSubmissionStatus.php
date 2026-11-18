<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssignmentSubmissionStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case Graded = 'graded';
    case Returned = 'returned';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Graded => 'Graded',
            self::Returned => 'Returned',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Graded => 'success',
            self::Returned => 'info',
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
