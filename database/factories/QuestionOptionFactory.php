<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionOption>
 */
class QuestionOptionFactory extends Factory
{
    protected $model = QuestionOption::class;

    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'text' => fake()->words(3, true),
            'is_correct' => false,
            'sort_order' => 0,
        ];
    }

    public function correct(): static
    {
        return $this->state(fn () => ['is_correct' => true]);
    }
}
