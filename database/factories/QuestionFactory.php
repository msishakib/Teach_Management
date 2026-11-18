<?php

namespace Database\Factories;

use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'type' => QuestionType::SingleChoice,
            'text' => fake()->sentence().'?',
            'explanation' => fake()->sentence(),
            'marks' => 1,
            'difficulty' => QuestionDifficulty::Medium,
        ];
    }

    public function multipleChoice(): static
    {
        return $this->state(fn () => ['type' => QuestionType::MultipleChoice]);
    }

    public function trueFalse(): static
    {
        return $this->state(fn () => ['type' => QuestionType::TrueFalse]);
    }

    public function shortAnswer(): static
    {
        return $this->state(fn () => ['type' => QuestionType::ShortAnswer]);
    }
}
