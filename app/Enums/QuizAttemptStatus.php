<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QuizAttemptStatus: string implements HasColor, HasLabel
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Graded = 'graded';

    public function getLabel(): string
    {
        return match ($this) {
            self::InProgress => 'In progress',
            self::Submitted => 'Submitted',
            self::Graded => 'Graded',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::InProgress => 'warning',
            self::Submitted => 'info',
            self::Graded => 'success',
        };
    }

    public function isClosed(): bool
    {
        return $this !== self::InProgress;
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
