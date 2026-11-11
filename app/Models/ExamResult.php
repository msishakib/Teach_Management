<?php

namespace App\Models;

use App\Models\Concerns\FlushesHomepageCache;
use App\Enums\ExamResultStatus;
use App\Models\Scopes\PublishedExamResultScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamResult extends Model
{
    use FlushesHomepageCache, HasFactory;

    protected $fillable = [
        'exam_id',
        'student_id',
        'result_version_id',
        'total_marks',
        'full_marks',
        'percentage',
        'gpa',
        'letter_grade',
        'status',
        'rank_in_batch',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'total_marks' => 'decimal:2',
            'full_marks' => 'decimal:2',
            'percentage' => 'decimal:2',
            'gpa' => 'decimal:2',
            'status' => ExamResultStatus::class,
            'rank_in_batch' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new PublishedExamResultScope);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function resultVersion(): BelongsTo
    {
        return $this->belongsTo(ResultVersion::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExamResultItem::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }
}
