<?php

namespace Database\Factories;

use App\Enums\FeeFrequency;
use App\Models\Course;
use App\Models\FeeStructure;
use App\Models\FeeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeStructure>
 */
class FeeStructureFactory extends Factory
{
    protected $model = FeeStructure::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'fee_type_id' => FeeType::factory(),
            'amount' => fake()->randomFloat(2, 500, 5000),
            'frequency' => FeeFrequency::Monthly,
        ];
    }

    public function monthly(float|int|string $amount = 1000): static
    {
        return $this->state(fn () => [
            'amount' => $amount,
            'frequency' => FeeFrequency::Monthly,
        ]);
    }
}
