<?php

namespace App\Models;

use App\Enums\ShowAnswersAfter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Quiz extends Model
{
    use HasFactory;

    public const TIMER_GRACE_SECONDS = 30;

    protected $fillable = [
        'course_id',
        'lesson_id',
        'title',
        'time_limit_minutes',
        'pass_percent',
        'max_attempts',
        'shuffle_questions',
        'shuffle_options',
        'one_question_per_page',
        'show_answers_after',
        'available_from',
        'available_until',
    ];

    protected function casts(): array
    {
        return [
            'time_limit_minutes' => 'integer',
            'pass_percent' => 'decimal:2',
            'max_attempts' => 'integer',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'one_question_per_page' => 'boolean',
            'show_answers_after' => ShowAnswersAfter::class,
            'available_from' => 'datetime',
            'available_until' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'quiz_questions')
            ->withPivot(['id', 'sort_order', 'marks_override'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function isAvailable(?Carbon $at = null): bool
    {
        $at ??= now();

        if ($this->available_from !== null && $at->lt($this->available_from)) {
            return false;
        }

        if ($this->available_until !== null && $at->gt($this->available_until)) {
            return false;
        }

        return true;
    }

    public function deadlineFor(Carbon $startedAt): ?Carbon
    {
        if ($this->time_limit_minutes === null) {
            return null;
        }

        return $startedAt->copy()
            ->addMinutes((int) $this->time_limit_minutes)
            ->addSeconds(self::TIMER_GRACE_SECONDS);
    }

    public function marksFor(Question $question): float
    {
        $override = $question->pivot->marks_override ?? null;

        return $override !== null ? (float) $override : (float) $question->marks;
    }
}
