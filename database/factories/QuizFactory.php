<?php

namespace Database\Factories;

use App\Enums\ShowAnswersAfter;
use App\Models\Course;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    protected $model = Quiz::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'lesson_id' => null,
            'title' => fake()->sentence(3),
            'time_limit_minutes' => 30,
            'pass_percent' => 60,
            'max_attempts' => 2,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'one_question_per_page' => false,
            'show_answers_after' => ShowAnswersAfter::AfterSubmit,
            'available_from' => null,
            'available_until' => null,
        ];
    }
}
