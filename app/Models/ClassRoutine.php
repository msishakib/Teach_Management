<?php

namespace App\Models;

use App\Enums\WeekDay;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassRoutine extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'subject_id',
        'teacher_id',
        'day',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'day' => WeekDay::class,
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
