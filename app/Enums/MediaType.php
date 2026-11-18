<?php

namespace App\Enums;

enum MediaType: string
{
    case Image = 'image';
    case Document = 'document';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Image',
            self::Document => 'Document',
            self::Video => 'Video',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Image => 'heroicon-o-photo',
            self::Document => 'heroicon-o-document-text',
            self::Video => 'heroicon-o-film',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Image => 'info',
            self::Document => 'warning',
            self::Video => 'success',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }
}
