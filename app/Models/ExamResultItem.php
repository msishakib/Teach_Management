<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResultItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_result_id',
        'exam_subject_id',
        'marks_obtained',
        'letter_grade',
        'grade_point',
        'is_pass',
        'is_absent',
    ];

    protected function casts(): array
    {
        return [
            'marks_obtained' => 'decimal:2',
            'grade_point' => 'decimal:2',
            'is_pass' => 'boolean',
            'is_absent' => 'boolean',
        ];
    }

    public function examResult(): BelongsTo
    {
        return $this->belongsTo(ExamResult::class);
    }

    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }
}
