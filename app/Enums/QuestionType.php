<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QuestionType: string implements HasColor, HasLabel
{
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';
    case ShortAnswer = 'short_answer';

    public function getLabel(): string
    {
        return match ($this) {
            self::SingleChoice => 'Single choice',
            self::MultipleChoice => 'Multiple choice',
            self::TrueFalse => 'True / false',
            self::ShortAnswer => 'Short answer',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SingleChoice => 'primary',
            self::MultipleChoice => 'info',
            self::TrueFalse => 'warning',
            self::ShortAnswer => 'gray',
        };
    }

    public function isAutoGradable(): bool
    {
        return $this !== self::ShortAnswer;
    }

    public function allowsMultipleOptions(): bool
    {
        return $this === self::MultipleChoice;
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
