<?php

namespace Database\Factories;

use App\Enums\QuizAttemptStatus;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAttempt>
 */
class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'student_id' => Student::factory(),
            'started_at' => now(),
            'submitted_at' => null,
            'score' => null,
            'percent' => null,
            'status' => QuizAttemptStatus::InProgress,
            'attempt_no' => 1,
            'question_order' => [],
        ];
    }
}
