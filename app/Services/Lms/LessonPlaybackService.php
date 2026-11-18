<?php

namespace App\Services\Lms;

use App\Enums\MediaCategory;
use App\Models\Lesson;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;

/**
 * Resolves the playable payload for a lesson (embed URL or a short-lived signed
 * upload URL) after checking course access.
 */
class LessonPlaybackService
{
    public function __construct(
        protected CourseAccessService $access,
        protected MediaService $media,
    ) {}

    /**
     * @return array{video_type: string, url: string}|null
     */
    public function payload(?User $user, Lesson $lesson): ?array
    {
        abort_unless($this->access->canView($user, $lesson), 403);

        if (! $lesson->hasVideo()) {
            return null;
        }

        if ($lesson->isEmbed()) {
            $url = $lesson->embedUrl();

            abort_if($url === null, 404);

            return [
                'video_type' => 'embed',
                'url' => $url,
            ];
        }

        $media = $this->uploadedMedia($lesson);

        abort_unless($media instanceof Media, 404);

        return [
            'video_type' => 'upload',
            'url' => $this->media->temporaryVideoUrl($media, $user),
        ];
    }

    protected function uploadedMedia(Lesson $lesson): ?Media
    {
        $mediaId = $lesson->uploadedVideoId();

        $media = $mediaId !== null ? Media::query()->find($mediaId) : null;

        if (! $media instanceof Media) {
            $media = $lesson->getFirstMedia(MediaCategory::CourseVideo->value);
        }

        if (
            ! $media instanceof Media
            || $media->category() !== MediaCategory::CourseVideo
            || $media->model_type !== $lesson->getMorphClass()
            || (int) $media->model_id !== (int) $lesson->getKey()
        ) {
            return null;
        }

        return $media;
    }
}
