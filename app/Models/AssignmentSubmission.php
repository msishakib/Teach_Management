<?php

namespace App\Models;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\MediaCategory;
use App\Models\Concerns\HasManagedMedia;
use App\Models\Scopes\PortalStudentOwnedScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;

class AssignmentSubmission extends Model implements HasMedia
{
    use HasFactory, HasManagedMedia;

    protected $fillable = [
        'assignment_id',
        'student_id',
        'text',
        'status',
        'marks',
        'feedback',
        'graded_by',
        'submitted_at',
        'graded_at',
        'is_late',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssignmentSubmissionStatus::class,
            'marks' => 'decimal:2',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'is_late' => 'boolean',
        ];
    }

    /**
     * @return list<MediaCategory>
     */
    public function mediaCategories(): array
    {
        return [MediaCategory::AssignmentSubmission];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new PortalStudentOwnedScope);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function files()
    {
        return $this->getMedia(MediaCategory::AssignmentSubmission->value);
    }

    public function percentOfMax(): ?float
    {
        $assignment = $this->relationLoaded('assignment')
            ? $this->assignment
            : $this->assignment()->first();

        if (! $assignment instanceof Assignment || $this->marks === null || (float) $assignment->max_marks <= 0) {
            return null;
        }

        return round(((float) $this->marks / (float) $assignment->max_marks) * 100, 2);
    }

    public function passed(): bool
    {
        $percent = $this->percentOfMax();
        $assignment = $this->relationLoaded('assignment')
            ? $this->assignment
            : $this->assignment()->first();

        if ($percent === null || ! $assignment instanceof Assignment) {
            return false;
        }

        // Assignments share the course-completion pass bar with quizzes (60% default).
        return $percent >= 60.0
            && in_array($this->status, [
                AssignmentSubmissionStatus::Graded,
                AssignmentSubmissionStatus::Returned,
            ], true);
    }
}
