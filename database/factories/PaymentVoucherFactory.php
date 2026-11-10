<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentVoucherStatus;
use App\Models\Account;
use App\Models\PaymentVoucher;
use App\Support\LedgerAccounts;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentVoucher>
 */
class PaymentVoucherFactory extends Factory
{
    protected $model = PaymentVoucher::class;

    public function definition(): array
    {
        return [
            'voucher_no' => strtoupper(fake()->unique()->bothify('PAY-####-####')),
            'date' => now()->toDateString(),
            'payee_type' => null,
            'payee_id' => null,
            'account_id' => fn () => Account::query()->where('code', LedgerAccounts::RENT)->value('id'),
            'paid_from_account_id' => fn () => Account::query()->where('code', LedgerAccounts::CASH)->value('id'),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'method' => PaymentMethod::Cash,
            'attachment' => null,
            'narration' => fake()->optional()->sentence(),
            'status' => PaymentVoucherStatus::Draft,
            'journal_entry_id' => null,
            'teacher_salary_id' => null,
            'created_by' => null,
            'approved_by' => null,
            'approved_at' => null,
            'posted_at' => null,
        ];
    }
}
