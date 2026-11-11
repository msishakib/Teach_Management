<?php

namespace App\Models;

use App\Models\Concerns\FlushesHomepageCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use FlushesHomepageCache, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_no',
        'name',
        'email',
        'phone',
        'joining_date',
        'salary',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'salary' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function classRoutines(): HasMany
    {
        return $this->hasMany(ClassRoutine::class);
    }

    public function examSubjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class);
    }

    public function leaveRequests(): MorphMany
    {
        return $this->morphMany(LeaveRequest::class, 'requester');
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(TeacherSalary::class);
    }

    public function assignedBatchIds(): array
    {
        return $this->classRoutines()
            ->distinct()
            ->pluck('batch_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}

