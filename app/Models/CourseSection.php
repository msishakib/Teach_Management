<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'course_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $section): void {
            // Keep lessons in one piece when a section is removed.
            $section->lessons()->update(['section_id' => null]);
        });

        $flush = static fn (self $section) => Course::flushCurriculumCache((int) $section->course_id);

        static::saved($flush);
        static::deleted($flush);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'section_id')
            ->orderBy('sort_order')
            ->orderBy('order')
            ->orderBy('id');
    }
}
