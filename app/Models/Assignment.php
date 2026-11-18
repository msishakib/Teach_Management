<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'lesson_id',
        'title',
        'instructions',
        'due_at',
        'max_marks',
        'allow_late',
        'max_files',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'max_marks' => 'decimal:2',
            'allow_late' => 'boolean',
            'max_files' => 'integer',
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

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function isPastDue(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->due_at !== null && $at->gt($this->due_at);
    }

    public function acceptsSubmissions(?Carbon $at = null): bool
    {
        if (! $this->isPastDue($at)) {
            return true;
        }

        return (bool) $this->allow_late;
    }
}
