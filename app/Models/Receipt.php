<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_no',
        'student_id',
        'date',
        'method',
        'account_id',
        'amount',
        'journal_entry_id',
        'status',
        'idempotency_key',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'status' => ReceiptStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }
}
