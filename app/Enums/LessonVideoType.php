<?php

namespace App\Enums;

enum LessonVideoType: string
{
    case Embed = 'embed';
    case Upload = 'upload';

    public function label(): string
    {
        return match ($this) {
            self::Embed => 'Embed (YouTube / Vimeo)',
            self::Upload => 'Upload (mp4 / webm)',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
