<?php

namespace Database\Factories;

use App\Enums\AttendanceSessionStatus;
use App\Models\AttendanceSession;
use App\Models\Batch;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSession>
 */
class AttendanceSessionFactory extends Factory
{
    protected $model = AttendanceSession::class;

    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'subject_id' => null,
            'teacher_id' => Teacher::factory(),
            'date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'taken_by' => User::factory()->admin(),
            'status' => AttendanceSessionStatus::Open,
        ];
    }

    public function finalized(): static
    {
        return $this->state(fn () => [
            'status' => AttendanceSessionStatus::Finalized,
        ]);
    }
}
