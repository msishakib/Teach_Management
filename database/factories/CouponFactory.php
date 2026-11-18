<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE##')),
            'type' => CouponType::Percent,
            'value' => 10,
            'max_uses' => 100,
            'used_count' => 0,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'course_id' => null,
            'is_active' => true,
        ];
    }

    public function fixed(float $amount = 100): static
    {
        return $this->state(fn () => [
            'type' => CouponType::Fixed,
            'value' => $amount,
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_uses' => 1,
            'used_count' => 1,
        ]);
    }
}
