<?php

namespace App\Models;

use App\Enums\LeaveRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'requester_type',
        'requester_id',
        'from_date',
        'to_date',
        'reason',
        'status',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'status' => LeaveRequestStatus::class,
        ];
    }

    public function requester(): MorphTo
    {
        return $this->morphTo();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
