<?php

namespace Database\Factories;

use App\Enums\AssignmentSubmissionStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    protected $model = AssignmentSubmission::class;

    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'student_id' => Student::factory(),
            'text' => fake()->paragraph(),
            'status' => AssignmentSubmissionStatus::Submitted,
            'marks' => null,
            'feedback' => null,
            'graded_by' => null,
            'submitted_at' => now(),
            'graded_at' => null,
            'is_late' => false,
        ];
    }
}
