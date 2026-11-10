<?php

namespace Database\Factories;

use App\Models\Guardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    protected $model = Guardian::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->optional()->numerify('01#########'),
        ];
    }
}
