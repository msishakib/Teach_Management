<?php

namespace Database\Factories;

use App\Models\GradingScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradingScale>
 */
class GradingScaleFactory extends Factory
{
    protected $model = GradingScale::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Scale',
            'is_default' => false,
        ];
    }

    public function defaultScale(): static
    {
        return $this->state(fn () => [
            'name' => 'Default GPA Scale',
            'is_default' => true,
        ]);
    }
}
