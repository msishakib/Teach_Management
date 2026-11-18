<?php

namespace App\Models;

use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'type',
        'text',
        'explanation',
        'marks',
        'difficulty',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'difficulty' => QuestionDifficulty::class,
            'marks' => 'decimal:2',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class, 'quiz_questions')
            ->withPivot(['sort_order', 'marks_override'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function correctOptionIds(): array
    {
        return $this->options
            ->where('is_correct', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
