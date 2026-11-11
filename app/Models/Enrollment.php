<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Scopes\PortalStudentOwnedScope;
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
    ];

    protected function casts(): array
    {
        return [
            'enrolled_on' => 'date',
            'status' => EnrollmentStatus::class,
        ];
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
