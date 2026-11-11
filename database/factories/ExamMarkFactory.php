<?php

namespace Database\Factories;

use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamMark>
 */
class ExamMarkFactory extends Factory
{
    protected $model = ExamMark::class;

    public function definition(): array
    {
        return [
            'exam_subject_id' => ExamSubject::factory(),
            'student_id' => Student::factory(),
            'marks_obtained' => fake()->randomFloat(2, 0, 100),
            'is_absent' => false,
            'remarks' => null,
            'entered_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function absent(): static
    {
        return $this->state(fn () => [
            'marks_obtained' => null,
            'is_absent' => true,
        ]);
    }
}
