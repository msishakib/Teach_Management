<?php

namespace App\Models;

use App\Enums\TeacherAttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'date',
        'check_in',
        'check_out',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => TeacherAttendanceStatus::class,
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
