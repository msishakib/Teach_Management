<?php

namespace Database\Factories;

use App\Enums\AcademicSessionStatus;
use App\Models\AcademicSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicSession>
 */
class AcademicSessionFactory extends Factory
{
    protected $model = AcademicSession::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 year', '+1 month');

        return [
            'name' => fake()->unique()->numerify('Session ####'),
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+1 year'),
            'status' => AcademicSessionStatus::Upcoming,
            'is_current' => false,
        ];
    }

    public function current(): static
    {
        return $this->state(fn () => [
            'status' => AcademicSessionStatus::Active,
            'is_current' => true,
        ]);
    }
}
