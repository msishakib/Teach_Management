<?php

namespace App\Models;

use App\Enums\QuizAttemptStatus;
use App\Models\Scopes\PortalStudentOwnedScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class QuizAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'student_id',
        'started_at',
        'submitted_at',
        'score',
        'percent',
        'status',
        'attempt_no',
        'question_order',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
            'percent' => 'decimal:2',
            'status' => QuizAttemptStatus::class,
            'attempt_no' => 'integer',
            'question_order' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new PortalStudentOwnedScope);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'attempt_id');
    }

    public function isOpen(): bool
    {
        return $this->status === QuizAttemptStatus::InProgress;
    }

    public function isTimedOut(?Carbon $at = null): bool
    {
        $at ??= now();
        $quiz = $this->relationLoaded('quiz') ? $this->quiz : $this->quiz()->first();

        if (! $quiz instanceof Quiz) {
            return false;
        }

        $deadline = $quiz->deadlineFor($this->started_at);

        return $deadline !== null && $at->gt($deadline);
    }

    public function passed(): bool
    {
        $quiz = $this->relationLoaded('quiz') ? $this->quiz : $this->quiz()->first();

        if (! $quiz instanceof Quiz || $this->percent === null) {
            return false;
        }

        return (float) $this->percent >= (float) $quiz->pass_percent;
    }
}
