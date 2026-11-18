<?php

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'gateway',
        'request_payload',
        'response_payload',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'request_payload' => 'array',
            'response_payload' => 'array',
            'status' => PaymentAttemptStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
