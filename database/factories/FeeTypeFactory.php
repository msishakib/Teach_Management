<?php

namespace Database\Factories;

use App\Models\FeeType;
use App\Support\LedgerAccounts;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeType>
 */
class FeeTypeFactory extends Factory
{
    protected $model = FeeType::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('FEE-###')),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'income_account_id' => null,
            'is_active' => true,
        ];
    }

    public function tuition(): static
    {
        return $this->state(fn () => [
            'code' => 'TUITION',
            'name' => 'Tuition Fee',
            'income_account_id' => LedgerAccounts::byCode(LedgerAccounts::TUITION_FEE_INCOME)->id,
        ]);
    }

    public function exam(): static
    {
        return $this->state(fn () => [
            'code' => 'EXAM',
            'name' => 'Exam Fee',
            'income_account_id' => LedgerAccounts::byCode(LedgerAccounts::EXAM_FEE_INCOME)->id,
        ]);
    }
}
