<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Notice */
class NoticeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'audience' => $this->audience?->value ?? $this->audience,
            'is_pinned' => (bool) $this->is_pinned,
            'published_at' => $this->published_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'batch' => $this->whenLoaded('batch', fn () => [
                'id' => $this->batch->id,
                'name' => $this->batch->name,
            ]),
        ];
    }
}
