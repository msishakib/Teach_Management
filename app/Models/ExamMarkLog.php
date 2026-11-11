<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamMarkLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'exam_mark_id',
        'old_marks',
        'new_marks',
        'changed_by',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_marks' => 'decimal:2',
            'new_marks' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function examMark(): BelongsTo
    {
        return $this->belongsTo(ExamMark::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
