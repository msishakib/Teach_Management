<?php

namespace App\Models;

use App\Enums\AttendanceSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'subject_id',
        'teacher_id',
        'date',
        'start_time',
        'taken_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceSessionStatus::class,
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    public function studentAttendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class);
    }

    public function isFinalized(): bool
    {
        return $this->status === AttendanceSessionStatus::Finalized;
    }
}
