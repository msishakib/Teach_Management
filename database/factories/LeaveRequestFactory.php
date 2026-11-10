<?php

namespace Database\Factories;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $from = now()->subDay();

        return [
            'requester_type' => Student::class,
            'requester_id' => Student::factory(),
            'from_date' => $from->toDateString(),
            'to_date' => $from->copy()->addDays(2)->toDateString(),
            'reason' => fake()->sentence(),
            'status' => LeaveRequestStatus::Pending,
            'approved_by' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => LeaveRequestStatus::Approved,
        ]);
    }
}
