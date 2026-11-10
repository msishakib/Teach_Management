<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'admission_no' => strtoupper(fake()->unique()->bothify('ADM-####-####')),
            'name' => fake()->name(),
            'dob' => fake()->optional()->dateTimeBetween('-20 years', '-5 years'),
            'gender' => fake()->optional()->randomElement(Gender::cases()),
            'email' => fake()->boolean() ? fake()->unique()->safeEmail() : null,
            'phone' => fake()->boolean() ? fake()->numerify('01#########') : null,
            'address' => fake()->boolean() ? fake()->address() : null,
            'photo' => null,
            'status' => StudentStatus::Active,
        ];
    }
}
