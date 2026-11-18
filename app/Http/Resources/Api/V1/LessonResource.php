<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lesson */
class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'section_id' => $this->section_id,
            'title' => $this->title,
            'type' => $this->type?->value ?? $this->type,
            'content' => $this->content,
            'video_type' => $this->video_type?->value ?? $this->video_type,
            'video_url' => $this->embedUrl(),
            'duration_seconds' => (int) ($this->duration_seconds ?? $this->duration ?? 0),
            'is_free_preview' => (bool) $this->is_free_preview,
            'is_published' => (bool) $this->is_published,
            'sort_order' => (int) $this->sort_order,
            'is_completed' => $this->when(isset($this->is_completed), (bool) $this->is_completed),
            'is_locked' => $this->when(isset($this->is_locked), (bool) $this->is_locked),
            'resources' => $this->whenLoaded('resources', fn () => $this->resources->map(fn ($resource) => [
                'id' => $resource->id,
                'title' => $resource->title,
                'download_url' => $resource->media_id
                    ? route('lessons.resources.download', ['resource' => $resource->id])
                    : null,
            ])->values()),
        ];
    }
}
