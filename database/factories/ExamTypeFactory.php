<?php

namespace Database\Factories;

use App\Enums\ExamTypeStatus;
use App\Models\ExamType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamType>
 */
class ExamTypeFactory extends Factory
{
    protected $model = ExamType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Class Test', 'Midterm', 'Final', 'Monthly']),
            'weight' => fake()->optional()->randomFloat(2, 5, 40),
            'status' => ExamTypeStatus::Active,
        ];
    }
}
