<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'lesson_id' => null,
            'title' => fake()->sentence(3),
            'instructions' => fake()->paragraph(),
            'due_at' => now()->addWeek(),
            'max_marks' => 100,
            'allow_late' => false,
            'max_files' => 3,
        ];
    }
}
