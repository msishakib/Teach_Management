<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ResultVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultVersion>
 */
class ResultVersionFactory extends Factory
{
    protected $model = ResultVersion::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'version' => 1,
            'processed_by' => null,
            'processed_at' => now(),
            'snapshot' => [],
            'notes' => null,
        ];
    }
}
