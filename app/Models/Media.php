<?php

namespace App\Models;

use App\Enums\MediaCategory;
use App\Enums\MediaType;
use App\Models\Concerns\FlushesHomepageCache;
use App\Services\HomepageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Media extends SpatieMedia
{
    protected static function booted(): void
    {
        $flushHomepage = static function (self $media): void {
            $owner = $media->model;

            if (
                $owner !== null
                && in_array(FlushesHomepageCache::class, class_uses_recursive($owner), true)
            ) {
                app(HomepageService::class)->flush();
            }
        };

        static::saved($flushHomepage);
        static::deleted($flushHomepage);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function category(): ?MediaCategory
    {
        return MediaCategory::tryFrom($this->collection_name);
    }

    public function mediaType(): MediaType
    {
        return $this->category()?->type() ?? MediaType::Document;
    }

    public function isPrivate(): bool
    {
        return (bool) $this->category()?->isAccessRestricted();
    }

    /**
     * A human readable original file name, falling back to the stored name.
     */
    public function displayName(): string
    {
        return (string) ($this->original_name ?: $this->name ?: $this->file_name);
    }

    /**
     * Public URL for public media. Private documents and lesson videos must
     * be served through their access-checked routes instead.
     */
    public function publicUrl(?string $conversion = ''): ?string
    {
        if ($this->isPrivate()) {
            return null;
        }

        try {
            return $this->getUrl($conversion);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Responsive, public-safe image data for Blade and client-side galleries.
     *
     * @return array{src: string, srcset: string, width: int|null, height: int|null}|null
     */
    public function responsiveImageData(): ?array
    {
        $src = $this->publicUrl();

        if ($src === null || $this->mediaType() !== MediaType::Image) {
            return null;
        }

        $width = (int) $this->getCustomProperty('image_width', 0);
        $height = (int) $this->getCustomProperty('image_height', 0);
        $sources = collect(config('media.conversions', []))
            ->filter(fn ($targetWidth, $conversion): bool => $this->hasGeneratedConversion($conversion)
                && ($width === 0 || (int) $targetWidth < $width))
            ->map(function ($targetWidth, $conversion) use ($width): ?string {
                $url = $this->publicUrl($conversion);

                if ($url === null) {
                    return null;
                }

                $actualWidth = $width > 0 ? min($width, (int) $targetWidth) : (int) $targetWidth;

                return $url.' '.$actualWidth.'w';
            })
            ->filter();

        if ($width > 0) {
            $sources->push($src.' '.$width.'w');
        }

        return [
            'src' => $src,
            'srcset' => $sources->implode(', '),
            'width' => $width > 0 ? $width : null,
            'height' => $height > 0 ? $height : null,
        ];
    }

    public function scopeForCategory(Builder $query, MediaCategory $category): Builder
    {
        return $query->where('collection_name', $category->value);
    }

    public function scopeOfType(Builder $query, MediaType $type): Builder
    {
        return $query->whereIn('collection_name', array_map(
            fn (MediaCategory $category): string => $category->value,
            array_filter(MediaCategory::cases(), fn (MediaCategory $category): bool => $category->type() === $type),
        ));
    }
}
