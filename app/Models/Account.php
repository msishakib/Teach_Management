<?php

namespace App\Models;

use App\Enums\AccountKind;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'type',
        'is_group',
        'is_cash_or_bank',
        'opening_balance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'status' => AccountStatus::class,
            'is_group' => 'boolean',
            'is_cash_or_bank' => 'boolean',
            'opening_balance' => 'decimal:2',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function isPostable(): bool
    {
        return ! $this->is_group && $this->status === AccountStatus::Active;
    }

    public function kind(): AccountKind
    {
        if (! $this->is_group) {
            return AccountKind::Ledger;
        }

        return $this->parent_id === null
            ? AccountKind::Group
            : AccountKind::SubGroup;
    }

    public function label(): string
    {
        return "{$this->code} — {$this->name}";
    }
}
