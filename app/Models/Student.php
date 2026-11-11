<?php

namespace App\Models;

use App\Models\Concerns\FlushesHomepageCache;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use FlushesHomepageCache, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'admission_no',
        'name',
        'dob',
        'gender',
        'email',
        'phone',
        'address',
        'photo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'gender' => Gender::class,
            'status' => StudentStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->withPivot('relation')
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class);
    }

    public function leaveRequests(): MorphMany
    {
        return $this->morphMany(LeaveRequest::class, 'requester');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(StudentInvoice::class);
    }

    public function examResults(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }
}

