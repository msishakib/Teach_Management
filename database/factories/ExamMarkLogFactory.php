<?php

namespace Database\Factories;

use App\Models\ExamMark;
use App\Models\ExamMarkLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamMarkLog>
 */
class ExamMarkLogFactory extends Factory
{
    protected $model = ExamMarkLog::class;

    public function definition(): array
    {
        return [
            'exam_mark_id' => ExamMark::factory(),
            'old_marks' => fake()->optional()->randomFloat(2, 0, 100),
            'new_marks' => fake()->optional()->randomFloat(2, 0, 100),
            'changed_by' => User::factory(),
            'reason' => fake()->sentence(),
            'created_at' => now(),
        ];
    }
}
