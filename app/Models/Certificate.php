<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'exam_id',
        'certificate_no',
        'verify_token',
        'issued_on',
        'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Certificate $certificate): void {
            if (blank($certificate->verify_token)) {
                $certificate->verify_token = Str::random(40);
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function publicPayload(): array
    {
        return [
            'certificate_no' => $this->certificate_no,
            'student_name' => $this->student?->name,
            'course_name' => $this->course?->name,
            'issued_on' => $this->issued_on?->toDateString(),
        ];
    }
}
