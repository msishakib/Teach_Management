<?php

namespace Database\Factories;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentGateway;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'gateway' => PaymentGateway::Fake,
            'request_payload' => ['demo' => true],
            'response_payload' => null,
            'status' => PaymentAttemptStatus::Pending,
            'created_at' => now(),
        ];
    }
}
