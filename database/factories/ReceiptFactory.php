<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use App\Models\Account;
use App\Models\Receipt;
use App\Models\Student;
use App\Support\LedgerAccounts;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    public function definition(): array
    {
        return [
            'receipt_no' => strtoupper(fake()->unique()->bothify('RCPT-####-####')),
            'student_id' => Student::factory(),
            'date' => now()->toDateString(),
            'method' => PaymentMethod::Cash,
            'account_id' => fn () => Account::query()->where('code', LedgerAccounts::CASH)->value('id')
                ?? Account::factory()->cashOrBank()->create()->id,
            'amount' => fake()->randomFloat(2, 100, 2000),
            'journal_entry_id' => null,
            'status' => ReceiptStatus::Posted,
            'idempotency_key' => null,
            'created_by' => null,
            'notes' => null,
        ];
    }
}
