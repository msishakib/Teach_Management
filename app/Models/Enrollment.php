<?php

namespace App\Models;

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Models\Scopes\PortalStudentOwnedScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new PortalStudentOwnedScope);
    }

    protected $fillable = [
        'student_id',
        'batch_id',
        'enrolled_on',
        'status',
        'enrolled_via',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_on' => 'date',
            'status' => EnrollmentStatus::class,
            'enrolled_via' => EnrollmentSource::class,
            'expires_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::Active);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
