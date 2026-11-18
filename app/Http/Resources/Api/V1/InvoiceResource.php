<?php

namespace App\Http\Resources\Api\V1;

use App\Models\StudentInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentInvoice */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'month' => $this->month,
            'due_date' => $this->due_date?->toDateString(),
            'total' => $this->total,
            'discount' => $this->discount,
            'paid' => $this->paid,
            'due' => $this->due,
            'status' => $this->status?->value ?? $this->status,
            'batch' => $this->whenLoaded('batch', fn () => [
                'id' => $this->batch->id,
                'name' => $this->batch->name,
                'course' => $this->batch->relationLoaded('course') ? [
                    'id' => $this->batch->course?->id,
                    'name' => $this->batch->course?->name,
                ] : null,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'amount' => $item->amount,
                'fee_type' => $item->relationLoaded('feeType') ? [
                    'id' => $item->feeType?->id,
                    'name' => $item->feeType?->name,
                ] : null,
            ])->values()),
        ];
    }
}
