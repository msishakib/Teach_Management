<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\ReceiveVoucherStatus;
use App\Models\Account;
use App\Models\ReceiveVoucher;
use App\Support\LedgerAccounts;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiveVoucher>
 */
class ReceiveVoucherFactory extends Factory
{
    protected $model = ReceiveVoucher::class;

    public function definition(): array
    {
        return [
            'voucher_no' => strtoupper(fake()->unique()->bothify('RV-####-####')),
            'date' => now()->toDateString(),
            'payer_type' => null,
            'payer_id' => null,
            'account_id' => fn () => Account::query()->where('code', LedgerAccounts::TUITION_FEE_INCOME)->value('id'),
            'received_to_account_id' => fn () => Account::query()->where('code', LedgerAccounts::CASH)->value('id'),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'method' => PaymentMethod::Cash,
            'attachment' => null,
            'narration' => fake()->optional()->sentence(),
            'status' => ReceiveVoucherStatus::Draft,
            'journal_entry_id' => null,
            'created_by' => null,
            'approved_by' => null,
            'approved_at' => null,
            'posted_at' => null,
        ];
    }
}
