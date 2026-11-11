<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'exam_id' => null,
            'certificate_no' => 'CERT-'.fake()->unique()->numerify('2026-####'),
            'verify_token' => Str::random(40),
            'issued_on' => now()->toDateString(),
            'issued_by' => null,
        ];
    }
}
