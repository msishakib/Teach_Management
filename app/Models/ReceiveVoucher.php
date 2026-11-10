<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ReceiveVoucherStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReceiveVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_no',
        'date',
        'payer_type',
        'payer_id',
        'account_id',
        'received_to_account_id',
        'amount',
        'method',
        'attachment',
        'narration',
        'status',
        'journal_entry_id',
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
            'status' => ReceiveVoucherStatus::class,
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function payer(): MorphTo
    {
        return $this->morphTo();
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function receivedToAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'received_to_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
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
        return $this->status === ReceiveVoucherStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === ReceiveVoucherStatus::Posted;
    }
}
