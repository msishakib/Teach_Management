<?php

namespace App\Models;

use App\Enums\MediaCategory;
use App\Models\Concerns\HasManagedMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class LessonResource extends Model implements HasMedia
{
    use HasFactory, HasManagedMedia;

    protected $fillable = [
        'lesson_id',
        'title',
        'media_id',
    ];

    protected function casts(): array
    {
        return [
            'lesson_id' => 'integer',
            'media_id' => 'integer',
        ];
    }

    /**
     * @return list<MediaCategory>
     */
    public function mediaCategories(): array
    {
        return [MediaCategory::LessonResource];
    }

    protected static function booted(): void
    {
        static::saved(function (self $resource): void {
            // When a file is uploaded through the media library, mirror its id
            // onto the explicit media_id column used by the download route.
            if ($resource->media_id === null) {
                $media = $resource->getFirstMedia(MediaCategory::LessonResource->value);

                if ($media instanceof SpatieMedia) {
                    $resource->forceFill(['media_id' => (int) $media->getKey()])->saveQuietly();
                }
            }

            $resource->flushCurriculumCache();
        });

        static::deleted(fn (self $resource) => $resource->flushCurriculumCache());
    }

    protected function flushCurriculumCache(): void
    {
        $courseId = Lesson::query()
            ->whereKey($this->lesson_id)
            ->value('course_id');

        if ($courseId !== null) {
            Course::flushCurriculumCache((int) $courseId);
        }
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * The explicit media row linked from media_id. Named fileMedia() because
     * Spatie's HasMedia contract already owns the media() relation.
     */
    public function fileMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /**
     * The downloadable media record, falling back to the media collection.
     */
    public function file(): ?Media
    {
        if ($this->fileMedia instanceof Media) {
            return $this->fileMedia;
        }

        $media = $this->getFirstMedia(MediaCategory::LessonResource->value);

        return $media instanceof Media ? $media : null;
    }
}
