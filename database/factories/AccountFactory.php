<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'code' => (string) fake()->unique()->numerify('9###'),
            'name' => fake()->unique()->words(2, true),
            'type' => AccountType::Asset,
            'is_group' => false,
            'is_cash_or_bank' => false,
            'opening_balance' => 0,
            'status' => AccountStatus::Active,
        ];
    }

    public function group(): static
    {
        return $this->state(fn () => ['is_group' => true]);
    }

    public function cashOrBank(): static
    {
        return $this->state(fn () => ['is_cash_or_bank' => true]);
    }

    public function ofType(AccountType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }
}
