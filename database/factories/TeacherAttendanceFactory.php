<?php

namespace Database\Factories;

use App\Enums\TeacherAttendanceStatus;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherAttendance>
 */
class TeacherAttendanceFactory extends Factory
{
    protected $model = TeacherAttendance::class;

    public function definition(): array
    {
        return [
            'teacher_id' => Teacher::factory(),
            'date' => now()->toDateString(),
            'check_in' => '09:00:00',
            'check_out' => '17:00:00',
            'status' => TeacherAttendanceStatus::Present,
        ];
    }
}
