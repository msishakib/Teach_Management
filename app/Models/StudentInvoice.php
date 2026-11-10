<?php

namespace App\Models;

use App\Enums\StudentInvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'batch_id',
        'exam_id',
        'invoice_no',
        'month',
        'due_date',
        'total',
        'discount',
        'paid',
        'due',
        'status',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'total' => 'decimal:2',
            'discount' => 'decimal:2',
            'paid' => 'decimal:2',
            'due' => 'decimal:2',
            'status' => StudentInvoiceStatus::class,
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

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StudentInvoiceItem::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function recalculateStatus(): StudentInvoiceStatus
    {
        if (bccomp((string) $this->due, '0', 2) <= 0) {
            return StudentInvoiceStatus::Paid;
        }

        if (bccomp((string) $this->paid, '0', 2) > 0) {
            return StudentInvoiceStatus::Partial;
        }

        return StudentInvoiceStatus::Unpaid;
    }
}
