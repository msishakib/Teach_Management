<?php

namespace Database\Factories;

use App\Enums\BatchStatus;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    protected $model = Batch::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'name' => fake()->bothify('Batch-##'),
            'course_id' => Course::factory(),
            'session_id' => AcademicSession::factory(),
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+6 months'),
            'capacity' => fake()->numberBetween(10, 60),
            'status' => BatchStatus::Open,
        ];
    }
}
