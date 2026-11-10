<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalanceDaily extends Model
{
    protected $table = 'account_balances_daily';

    protected $fillable = [
        'account_id',
        'date',
        'debit_total',
        'credit_total',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'debit_total' => 'decimal:2',
            'credit_total' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
