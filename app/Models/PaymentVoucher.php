<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentVoucherStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_no',
        'date',
        'payee_type',
        'payee_id',
        'account_id',
        'paid_from_account_id',
        'amount',
        'method',
        'attachment',
        'narration',
        'status',
        'journal_entry_id',
        'teacher_salary_id',
        'created_by',
        'approved_by',
        'approved_at',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'status' => PaymentVoucherStatus::class,
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function payee(): MorphTo
    {
        return $this->morphTo();
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function paidFromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function teacherSalary(): BelongsTo
    {
        return $this->belongsTo(TeacherSalary::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDraft(): bool
    {
        return $this->status === PaymentVoucherStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === PaymentVoucherStatus::Posted;
    }
}
