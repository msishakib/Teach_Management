<?php

namespace App\Models;

use App\Enums\StudentAttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_session_id',
        'student_id',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentAttendanceStatus::class,
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
