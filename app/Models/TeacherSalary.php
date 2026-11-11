<?php

namespace App\Models;

use App\Enums\TeacherSalaryStatus;
use App\Models\Scopes\PortalTeacherOwnedScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TeacherSalary extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new PortalTeacherOwnedScope);
    }


    protected $fillable = [
        'teacher_id',
        'month',
        'amount',
        'deductions',
        'bonus',
        'net',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'deductions' => 'decimal:2',
            'bonus' => 'decimal:2',
            'net' => 'decimal:2',
            'status' => TeacherSalaryStatus::class,
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function paymentVoucher(): HasOne
    {
        return $this->hasOne(PaymentVoucher::class);
    }

    public function canPay(): bool
    {
        return $this->status->canPay();
    }
}
