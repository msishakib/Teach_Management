<?php

namespace Database\Factories;

use App\Enums\WeekDay;
use App\Models\Batch;
use App\Models\ClassRoutine;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassRoutine>
 */
class ClassRoutineFactory extends Factory
{
    protected $model = ClassRoutine::class;

    public function definition(): array
    {
        $startHour = fake()->numberBetween(8, 16);

        return [
            'batch_id' => Batch::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => Teacher::factory(),
            'day' => fake()->randomElement(WeekDay::cases()),
            'start_time' => sprintf('%02d:00:00', $startHour),
            'end_time' => sprintf('%02d:00:00', $startHour + 1),
        ];
    }
}
