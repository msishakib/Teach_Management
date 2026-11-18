<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_no' => $this->order_no,
            'amount' => $this->amount,
            'discount' => $this->discount,
            'net_amount' => $this->net_amount,
            'status' => $this->status?->value ?? $this->status,
            'gateway' => $this->gateway?->value ?? $this->gateway,
            'gateway_txn_id' => $this->gateway_txn_id,
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'course' => $this->whenLoaded('course', fn () => [
                'id' => $this->course->id,
                'name' => $this->course->name,
                'code' => $this->course->code,
            ]),
            'payment_attempts' => $this->whenLoaded('paymentAttempts', fn () => $this->paymentAttempts->map(fn ($attempt) => [
                'id' => $attempt->id,
                'gateway' => $attempt->gateway?->value ?? $attempt->gateway,
                'status' => $attempt->status?->value ?? $attempt->status,
                'created_at' => $attempt->created_at?->toISOString(),
            ])->values()),
        ];
    }
}
