<?php

namespace Database\Factories;

use App\Enums\TeacherSalaryStatus;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherSalary>
 */
class TeacherSalaryFactory extends Factory
{
    protected $model = TeacherSalary::class;

    public function definition(): array
    {
        $amount = number_format((float) fake()->randomFloat(2, 10000, 50000), 2, '.', '');
        $bonus = '0.00';
        $deductions = '0.00';

        return [
            'teacher_id' => Teacher::factory()->state(['salary' => $amount]),
            'month' => now()->format('Y-m'),
            'amount' => $amount,
            'deductions' => $deductions,
            'bonus' => $bonus,
            'net' => $amount,
            'status' => TeacherSalaryStatus::Generated,
        ];
    }
}
