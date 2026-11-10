<?php

namespace App\Models;

use App\Enums\JournalEntryStatus;
use App\Enums\VoucherType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_no',
        'entry_date',
        'voucher_type',
        'reference_type',
        'reference_id',
        'narration',
        'status',
        'created_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'voucher_type' => VoucherType::class,
            'status' => JournalEntryStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (JournalEntry $entry): void {
            $originalStatus = self::normalizeStatus($entry->getOriginal('status'));

            if ($originalStatus === JournalEntryStatus::Reversed) {
                throw new LogicException('Reversed journal entries are immutable.');
            }

            if ($originalStatus === JournalEntryStatus::Posted) {
                $dirty = array_keys($entry->getDirty());
                $allowed = ['status', 'updated_at'];

                if ($entry->status !== JournalEntryStatus::Reversed || array_diff($dirty, $allowed) !== []) {
                    throw new LogicException('Posted journal entries are immutable; reverse them instead.');
                }
            }
        });

        static::deleting(function (JournalEntry $entry): void {
            if ($entry->status !== JournalEntryStatus::Draft) {
                throw new LogicException('Only draft journal entries can be deleted.');
            }
        });
    }

    protected static function normalizeStatus(mixed $status): ?JournalEntryStatus
    {
        if ($status instanceof JournalEntryStatus) {
            return $status;
        }

        if ($status === null) {
            return null;
        }

        return JournalEntryStatus::tryFrom((string) $status);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function isDraft(): bool
    {
        return $this->status === JournalEntryStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === JournalEntryStatus::Posted;
    }

    public function isReversed(): bool
    {
        return $this->status === JournalEntryStatus::Reversed;
    }
}
