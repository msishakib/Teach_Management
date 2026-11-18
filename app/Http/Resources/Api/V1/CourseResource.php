<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Course */
class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'delivery_mode' => $this->delivery_mode?->value ?? $this->delivery_mode,
            'access_days' => $this->access_days,
            'lessons_count' => $this->whenCounted('lessons'),
            'completed_lessons_count' => $this->when(isset($this->completed_lessons_count), $this->completed_lessons_count),
            'progress_percent' => $this->when(isset($this->completed_lessons_count) && isset($this->lessons_count), function () {
                return $this->lessons_count > 0
                    ? round(($this->completed_lessons_count / $this->lessons_count) * 100, 2)
                    : 0;
            }),
            'is_completed' => $this->when(isset($this->is_completed), (bool) $this->is_completed),
            'enrolled_at' => $this->when(isset($this->enrolled_on), fn () => $this->enrolled_on?->toDateString()),
            'sections' => $this->whenLoaded('sections', fn () => $this->sections->map(fn ($section) => [
                'id' => $section->id,
                'title' => $section->title,
                'lessons' => $section->relationLoaded('lessons') ? $section->lessons->map(fn ($lesson) => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'type' => $lesson->type?->value ?? $lesson->type,
                    'duration_seconds' => (int) ($lesson->duration_seconds ?? $lesson->duration ?? 0),
                    'is_free_preview' => (bool) $lesson->is_free_preview,
                ])->values() : null,
            ])->values()),
        ];
    }
}
