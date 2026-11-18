<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Models\Course;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $amount = number_format((float) fake()->randomFloat(2, 100, 5000), 2, '.', '');

        return [
            'order_no' => 'ORD-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'student_id' => Student::factory(),
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'amount' => $amount,
            'discount' => '0.00',
            'coupon_id' => null,
            'net_amount' => $amount,
            'status' => OrderStatus::Pending,
            'gateway' => PaymentGateway::Fake,
            'gateway_txn_id' => null,
            'paid_at' => null,
            'idempotency_key' => fake()->uuid(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'gateway_txn_id' => 'FAKE-'.strtoupper(fake()->bothify('????####')),
        ]);
    }
}
