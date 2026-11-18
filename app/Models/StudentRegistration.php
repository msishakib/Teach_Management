<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRegistration extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'registration_no',
        'name',
        'dob',
        'gender',
        'phone',
        'email',
        'national_id',
        'birth_certificate_no',
        'religion',
        'blood_group',
        'present_address',
        'permanent_address',
        'last_institution',
        'last_exam',
        'last_result',
        'passing_year',
        'status',
        'remarks',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'passing_year' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
