<?php

namespace Database\Factories;

use App\Enums\ExamResultStatus;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamResult>
 */
class ExamResultFactory extends Factory
{
    protected $model = ExamResult::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'student_id' => Student::factory(),
            'result_version_id' => null,
            'total_marks' => 250,
            'full_marks' => 300,
            'percentage' => 83.33,
            'gpa' => 4.50,
            'letter_grade' => 'A+',
            'status' => ExamResultStatus::Pass,
            'rank_in_batch' => 1,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'published_at' => now(),
        ]);
    }
}
