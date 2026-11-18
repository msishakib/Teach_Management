<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LessonType: string implements HasColor, HasLabel
{
    case Video = 'video';
    case Text = 'text';
    case Pdf = 'pdf';
    case Quiz = 'quiz';
    case Assignment = 'assignment';
    case Live = 'live';

    public function getLabel(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::Text => 'Text',
            self::Pdf => 'PDF',
            self::Quiz => 'Quiz',
            self::Assignment => 'Assignment',
            self::Live => 'Live class',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Video => 'primary',
            self::Text => 'gray',
            self::Pdf => 'danger',
            self::Quiz => 'warning',
            self::Assignment => 'info',
            self::Live => 'success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Video => 'heroicon-o-play-circle',
            self::Text => 'heroicon-o-document-text',
            self::Pdf => 'heroicon-o-document',
            self::Quiz => 'heroicon-o-question-mark-circle',
            self::Assignment => 'heroicon-o-clipboard-document-check',
            self::Live => 'heroicon-o-signal',
        };
    }

    public function hasPlayableVideo(): bool
    {
        return $this === self::Video;
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
