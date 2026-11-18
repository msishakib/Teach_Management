<?php

namespace App\Models\Concerns;

use App\Enums\MediaCategory;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Registers the standard media collections/conversions declared by a model's
 * {@see mediaCategories()} method.
 */
trait HasManagedMedia
{
    use InteractsWithMedia;

    /**
     * The media categories this model accepts.
     *
     * @return list<MediaCategory>
     */
    abstract public function mediaCategories(): array;

    public function registerMediaCollections(): void
    {
        foreach ($this->mediaCategories() as $category) {
            $collection = $this->addMediaCollection($category->value)
                ->useDisk($category->disk())
                ->acceptsMimeTypes($category->acceptedMimeTypes());

            if (! $category->isMultiple()) {
                $collection->singleFile();
            }
        }
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $imageCollections = $this->imageCollectionNames();

        if ($imageCollections === []) {
            return;
        }

        foreach (config('media.conversions', []) as $name => $width) {
            $this->addMediaConversion($name)
                ->width($width)
                ->format('webp')
                ->quality((int) config('media.conversion_quality', 82))
                ->performOnCollections(...$imageCollections);
        }
    }

    /**
     * @return list<string>
     */
    protected function imageCollectionNames(): array
    {
        return array_values(array_map(
            fn (MediaCategory $category): string => $category->value,
            array_filter(
                $this->mediaCategories(),
                fn (MediaCategory $category): bool => $category->conversions() !== [],
            ),
        ));
    }

    public function mediaFor(MediaCategory $category): ?Media
    {
        return $this->getFirstMedia($category->value);
    }

    /**
     * URL of the first media item in a category (public disks only).
     */
    public function mediaUrl(MediaCategory $category, string $conversion = ''): ?string
    {
        $media = $this->mediaFor($category);

        if (! $media instanceof Media) {
            return null;
        }

        return $media->publicUrl($conversion);
    }

    /**
     * @return list<Media>
     */
    public function mediaItems(MediaCategory $category): array
    {
        return $this->getMedia($category->value)->all();
    }
}
