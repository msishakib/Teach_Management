<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSubject>
 */
class ExamSubjectFactory extends Factory
{
    protected $model = ExamSubject::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'subject_id' => Subject::factory(),
            'exam_date' => fake()->dateTimeBetween('now', '+1 month'),
            'start_time' => '10:00:00',
            'duration_minutes' => 90,
            'full_marks' => 100,
            'pass_marks' => 33,
            'teacher_id' => Teacher::factory(),
        ];
    }
}
