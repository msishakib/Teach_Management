<?php

namespace App\Models;

use App\Enums\FeeFrequency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeStructure extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'fee_type_id',
        'amount',
        'frequency',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'frequency' => FeeFrequency::class,
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }
}
