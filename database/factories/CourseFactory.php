<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('CRS-###')),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
