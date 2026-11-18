<?php

namespace App\Models;

use App\Enums\LessonType;
use App\Enums\LessonVideoType;
use App\Enums\MediaCategory;
use App\Models\Concerns\HasManagedMedia;
use App\Services\Media\VideoEmbedService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;

class Lesson extends Model implements HasMedia
{
    use HasFactory, HasManagedMedia;

    /**
     * @return list<MediaCategory>
     */
    public function mediaCategories(): array
    {
        return [MediaCategory::CourseVideo];
    }

    protected $fillable = [
        'course_id',
        'section_id',
        'title',
        'type',
        'content',
        'media_id',
        'order',
        'video_type',
        'video_url',
        'video_url_or_media_id',
        'duration',
        'duration_seconds',
        'is_free_preview',
        'is_published',
        'sort_order',
        'drip_after_days',
    ];

    protected function casts(): array
    {
        return [
            'section_id' => 'integer',
            'media_id' => 'integer',
            'type' => LessonType::class,
            'order' => 'integer',
            'video_type' => LessonVideoType::class,
            'duration' => 'integer',
            'duration_seconds' => 'integer',
            'is_free_preview' => 'boolean',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
            'drip_after_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $lesson): void {
            if ($lesson->type === null) {
                $lesson->type = LessonType::Video;
            }

            if ($lesson->video_type === null) {
                $lesson->video_type = LessonVideoType::Embed;
            }

            if ($lesson->is_published === null) {
                $lesson->is_published = true;
            }

            $sort = $lesson->sort_order ?? $lesson->order;
            $sort = max(0, (int) ($sort ?? 0));
            $lesson->sort_order = $sort;
            $lesson->order = $sort;

            $duration = $lesson->duration_seconds ?? $lesson->duration;

            if ($duration !== null) {
                $duration = max(0, (int) $duration);
                $lesson->duration_seconds = $duration;
                $lesson->duration = $duration;
            }

            if ($lesson->type === LessonType::Video) {
                $lesson->syncVideoSource();
            }
        });

        $flush = static fn (self $lesson) => Course::flushCurriculumCache((int) $lesson->course_id);

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * Keep the original video columns (used by the media module) and the new
     * LMS columns in sync, validating embeds/uploads exactly as before.
     */
    protected function syncVideoSource(): void
    {
        if ($this->video_type === LessonVideoType::Embed) {
            $url = $this->video_url ?: $this->video_url_or_media_id;

            if (filled($url)) {
                $embedUrl = app(VideoEmbedService::class)->embedUrl((string) $url);

                if ($embedUrl === null) {
                    throw ValidationException::withMessages([
                        'video_url' => 'Enter a valid YouTube or Vimeo URL.',
                    ]);
                }

                $this->video_url = $embedUrl;
                $this->video_url_or_media_id = $embedUrl;
            }

            return;
        }

        $mediaId = $this->media_id ?? $this->video_url_or_media_id;

        if (! filled($mediaId)) {
            return;
        }

        $media = Media::query()->find($mediaId);

        if (
            ! $media
            || $media->category() !== MediaCategory::CourseVideo
            || $media->model_type !== self::class
            || (int) $media->model_id !== (int) $this->getKey()
        ) {
            throw ValidationException::withMessages([
                'video_url_or_media_id' => 'Select an uploaded video belonging to this lesson.',
            ]);
        }

        $this->media_id = (int) $media->getKey();
        $this->video_url_or_media_id = (string) $media->getKey();
        $this->video_url = null;
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'section_id');
    }

    /**
     * The uploaded video media record, when the lesson uses the upload mode.
     */
    public function videoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'video_url_or_media_id');
    }

    /**
     * Optional file attached to the lesson itself (PDF, image, etc.).
     */
    public function primaryMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(LessonResource::class)->orderBy('id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('order')->orderBy('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeFreePreview(Builder $query): Builder
    {
        return $query->where('is_free_preview', true);
    }

    public function isPublished(): bool
    {
        return (bool) $this->is_published;
    }

    public function isEmbed(): bool
    {
        return $this->video_type === LessonVideoType::Embed;
    }

    public function isUpload(): bool
    {
        return $this->video_type === LessonVideoType::Upload;
    }

    public function hasVideo(): bool
    {
        return $this->type === LessonType::Video;
    }

    public function embedUrl(): ?string
    {
        if (! $this->hasVideo() || ! $this->isEmbed()) {
            return null;
        }

        return $this->video_url ?: $this->video_url_or_media_id;
    }

    public function uploadedVideoId(): ?int
    {
        if (! $this->hasVideo() || ! $this->isUpload()) {
            return null;
        }

        $id = $this->media_id ?: $this->video_url_or_media_id;

        return filled($id) ? (int) $id : null;
    }
}
