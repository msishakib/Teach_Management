<?php

namespace App\Models;

use App\Enums\ExamStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_session_id',
        'exam_type_id',
        'batch_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'fee_amount',
        'grading_scale_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => ExamStatus::class,
            'fee_amount' => 'decimal:2',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function gradingScale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(StudentInvoice::class);
    }

    public function hasFee(): bool
    {
        return $this->fee_amount !== null && bccomp((string) $this->fee_amount, '0', 2) > 0;
    }
}
