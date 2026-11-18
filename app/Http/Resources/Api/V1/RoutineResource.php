<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ClassRoutine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClassRoutine */
class RoutineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day' => $this->day?->value ?? $this->day,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'batch' => $this->whenLoaded('batch', fn () => [
                'id' => $this->batch->id,
                'name' => $this->batch->name,
                'course' => $this->batch->relationLoaded('course') ? [
                    'id' => $this->batch->course?->id,
                    'name' => $this->batch->course?->name,
                    'code' => $this->batch->course?->code,
                ] : null,
            ]),
            'subject' => $this->whenLoaded('subject', fn () => [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
                'code' => $this->subject->code ?? null,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->name,
            ]),
        ];
    }
}
