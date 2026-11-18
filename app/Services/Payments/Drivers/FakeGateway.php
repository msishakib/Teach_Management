<?php

namespace App\Services\Payments\Drivers;

use App\Models\Order;
use App\Services\Payments\GatewayInitiation;
use App\Services\Payments\GatewayValidationResult;
use App\Services\Payments\PaymentGatewayInterface;

/**
 * Local/test driver. Never enable as the production default.
 */
class FakeGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'fake';
    }

    public function initiate(Order $order, array $customer = []): GatewayInitiation
    {
        return new GatewayInitiation(
            mode: 'redirect',
            redirectUrl: route('payments.fake.pay', ['order' => $order->order_no]),
            meta: ['order_no' => $order->order_no],
        );
    }

    public function validateCallback(Order $order, array $payload): GatewayValidationResult
    {
        return $this->validate($order, $payload);
    }

    public function validateIpn(Order $order, array $payload): GatewayValidationResult
    {
        return $this->validate($order, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function validate(Order $order, array $payload): GatewayValidationResult
    {
        $status = strtolower((string) ($payload['status'] ?? 'success'));

        if (in_array($status, ['failed', 'fail', 'cancelled', 'cancel'], true)) {
            return new GatewayValidationResult(
                success: false,
                transactionId: isset($payload['tran_id']) ? (string) $payload['tran_id'] : null,
                amount: isset($payload['amount']) ? number_format((float) $payload['amount'], 2, '.', '') : null,
                payload: $payload,
                message: 'Payment was not successful.',
            );
        }

        $amount = number_format((float) ($payload['amount'] ?? 0), 2, '.', '');
        $expected = number_format((float) $order->net_amount, 2, '.', '');

        if (bccomp($amount, $expected, 2) !== 0) {
            return new GatewayValidationResult(
                success: false,
                transactionId: isset($payload['tran_id']) ? (string) $payload['tran_id'] : null,
                amount: $amount,
                payload: $payload,
                message: "Amount mismatch: expected {$expected}, got {$amount}.",
            );
        }

        $txn = (string) ($payload['tran_id'] ?? ('FAKE-'.$order->order_no));

        return new GatewayValidationResult(
            success: true,
            transactionId: $txn,
            amount: $expected,
            payload: $payload,
        );
    }
}
