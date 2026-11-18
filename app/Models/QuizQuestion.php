<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'question_id',
        'sort_order',
        'marks_override',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'marks_override' => 'decimal:2',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function effectiveMarks(): float
    {
        if ($this->marks_override !== null) {
            return (float) $this->marks_override;
        }

        $question = $this->relationLoaded('question')
            ? $this->question
            : $this->question()->first();

        return (float) ($question?->marks ?? 0);
    }
}
