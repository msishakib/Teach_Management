<?php

namespace Database\Factories;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        return [
            'employee_no' => strtoupper(fake()->unique()->bothify('EMP-####-####')),
            'name' => fake()->name(),
            'email' => fake()->boolean() ? fake()->unique()->safeEmail() : null,
            'phone' => fake()->boolean() ? fake()->numerify('01#########') : null,
            'joining_date' => fake()->boolean() ? fake()->date() : null,
            'salary' => fake()->boolean() ? fake()->randomFloat(2, 15000, 80000) : null,
        ];
    }
}
