<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'attempt_id',
        'question_id',
        'selected_option_ids',
        'answer_text',
        'is_correct',
        'marks_awarded',
    ];

    protected function casts(): array
    {
        return [
            'selected_option_ids' => 'array',
            'is_correct' => 'boolean',
            'marks_awarded' => 'decimal:2',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return list<int>
     */
    public function selectedOptionIdList(): array
    {
        return collect($this->selected_option_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
