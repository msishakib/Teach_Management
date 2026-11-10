<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SUB-###')),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
