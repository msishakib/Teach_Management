<?php

namespace Database\Factories;

use App\Models\GradingScale;
use App\Models\GradingScaleRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradingScaleRule>
 */
class GradingScaleRuleFactory extends Factory
{
    protected $model = GradingScaleRule::class;

    public function definition(): array
    {
        $min = fake()->numberBetween(0, 70);

        return [
            'grading_scale_id' => GradingScale::factory(),
            'min_percent' => $min,
            'max_percent' => min(100, $min + 10),
            'letter_grade' => fake()->randomElement(['A+', 'A', 'A-', 'B', 'C', 'D', 'F']),
            'grade_point' => fake()->randomFloat(2, 0, 5),
            'remark' => fake()->optional()->word(),
        ];
    }
}
