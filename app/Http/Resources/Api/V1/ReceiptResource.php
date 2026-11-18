<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Receipt */
class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_no' => $this->receipt_no,
            'date' => $this->date?->toDateString(),
            'method' => $this->method?->value ?? $this->method,
            'amount' => $this->amount,
            'status' => $this->status?->value ?? $this->status,
            'notes' => $this->notes,
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation) => [
                'id' => $allocation->id,
                'amount' => $allocation->amount,
                'invoice' => $allocation->relationLoaded('invoice') ? [
                    'id' => $allocation->invoice?->id,
                    'invoice_no' => $allocation->invoice?->invoice_no,
                ] : null,
            ])->values()),
        ];
    }
}
