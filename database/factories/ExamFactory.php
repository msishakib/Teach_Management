<?php

namespace Database\Factories;

use App\Enums\ExamStatus;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\GradingScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+1 month');

        return [
            'academic_session_id' => AcademicSession::factory(),
            'exam_type_id' => ExamType::factory(),
            'batch_id' => Batch::factory(),
            'name' => fake()->words(3, true).' Exam',
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+7 days'),
            'status' => ExamStatus::Draft,
            'fee_amount' => null,
            'grading_scale_id' => null,
        ];
    }

    public function withFee(float $amount = 500): static
    {
        return $this->state(fn () => [
            'fee_amount' => $amount,
        ]);
    }

    public function withGradingScale(): static
    {
        return $this->state(fn () => [
            'grading_scale_id' => GradingScale::factory(),
        ]);
    }
}
