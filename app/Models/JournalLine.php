<?php

namespace App\Models;

use App\Enums\JournalEntryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class JournalLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'student_id',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (JournalLine $line): void {
            $line->assertParentMutable();
        });

        static::deleting(function (JournalLine $line): void {
            $line->assertParentMutable();
        });
    }

    protected function assertParentMutable(): void
    {
        $status = JournalEntry::query()
            ->whereKey($this->journal_entry_id)
            ->value('status');

        $status = $status instanceof JournalEntryStatus
            ? $status
            : JournalEntryStatus::tryFrom((string) $status);

        if (in_array($status, [JournalEntryStatus::Posted, JournalEntryStatus::Reversed], true)) {
            throw new LogicException('Lines on posted or reversed journal entries are immutable.');
        }
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
