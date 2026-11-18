<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\MediaCategory;
use App\Enums\StudentStatus;
use App\Models\Concerns\FlushesHomepageCache;
use App\Models\Concerns\HasManagedMedia;
use App\Models\Concerns\LogsActivityWithContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;

class Student extends Model implements HasMedia
{
    use FlushesHomepageCache, HasFactory, HasManagedMedia, LogsActivityWithContext, SoftDeletes;

    /**
     * @return list<MediaCategory>
     */
    public function mediaCategories(): array
    {
        return [MediaCategory::StudentPhoto, MediaCategory::StudentDocument];
    }

    protected $fillable = [
        'user_id',
        'admission_no',
        'name',
        'dob',
        'gender',
        'national_id',
        'birth_certificate_no',
        'religion',
        'blood_group',
        'email',
        'phone',
        'address',
        'present_address',
        'permanent_address',
        'last_institution',
        'last_exam',
        'last_result',
        'passing_year',
        'photo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'passing_year' => 'integer',
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
