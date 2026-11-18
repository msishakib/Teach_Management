<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QuestionDifficulty: string implements HasColor, HasLabel
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function getLabel(): string
    {
        return match ($this) {
            self::Easy => 'Easy',
            self::Medium => 'Medium',
            self::Hard => 'Hard',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Easy => 'success',
            self::Medium => 'warning',
            self::Hard => 'danger',
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
