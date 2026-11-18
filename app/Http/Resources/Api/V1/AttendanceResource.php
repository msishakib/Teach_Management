<?php

namespace App\Http\Resources\Api\V1;

use App\Models\StudentAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentAttendance */
class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status?->value ?? $this->status,
            'remarks' => $this->remarks,
            'session' => $this->whenLoaded('session', fn () => [
                'id' => $this->session->id,
                'date' => $this->session->date?->toDateString(),
                'start_time' => $this->session->start_time,
                'status' => $this->session->status?->value ?? $this->session->status,
                'subject' => $this->session->relationLoaded('subject') ? [
                    'id' => $this->session->subject?->id,
                    'name' => $this->session->subject?->name,
                ] : null,
                'batch' => $this->session->relationLoaded('batch') ? [
                    'id' => $this->session->batch?->id,
                    'name' => $this->session->batch?->name,
                    'course' => $this->session->batch?->relationLoaded('course') ? [
                        'id' => $this->session->batch->course?->id,
                        'name' => $this->session->batch->course?->name,
                    ] : null,
                ] : null,
            ]),
        ];
    }
}
