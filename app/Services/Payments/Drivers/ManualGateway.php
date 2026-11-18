<?php

namespace App\Services\Payments\Drivers;

use App\Models\Order;
use App\Services\Payments\GatewayInitiation;
use App\Services\Payments\GatewayValidationResult;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\SettingsService;

/**
 * Student sends money via bKash/Nagad and submits a TrxID; admin verifies.
 */
class ManualGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected SettingsService $settings,
        protected array $config = [],
    ) {}

    public function name(): string
    {
        return 'manual';
    }

    public function initiate(Order $order, array $customer = []): GatewayInitiation
    {
        return new GatewayInitiation(
            mode: 'manual',
            redirectUrl: route('payments.manual.show', ['order' => $order->order_no]),
            meta: [
                'bkash_number' => $this->settings->get('payments.manual_bkash_number')
                    ?: ($this->config['bkash_number'] ?? null),
                'nagad_number' => $this->settings->get('payments.manual_nagad_number')
                    ?: ($this->config['nagad_number'] ?? null),
                'instructions' => $this->settings->get('payments.manual_instructions')
                    ?: ($this->config['instructions'] ?? null),
            ],
        );
    }

    public function validateCallback(Order $order, array $payload): GatewayValidationResult
    {
        // Browser submission alone never confirms payment — admin verification required.
        return new GatewayValidationResult(
            success: false,
            transactionId: isset($payload['trx_id']) ? (string) $payload['trx_id'] : $order->manual_trx_id,
            amount: number_format((float) $order->net_amount, 2, '.', ''),
            payload: $payload,
            message: 'Manual payments require admin verification.',
        );
    }

    public function validateIpn(Order $order, array $payload): GatewayValidationResult
    {
        $verified = (bool) ($payload['admin_verified'] ?? false);

        if (! $verified) {
            return new GatewayValidationResult(
                success: false,
                payload: $payload,
                message: 'Admin verification flag missing.',
            );
        }

        $trx = (string) ($payload['trx_id'] ?? $order->manual_trx_id ?? '');

        if ($trx === '') {
            return new GatewayValidationResult(
                success: false,
                payload: $payload,
                message: 'TrxID is required.',
            );
        }

        $amount = number_format((float) ($payload['amount'] ?? $order->net_amount), 2, '.', '');
        $expected = number_format((float) $order->net_amount, 2, '.', '');

        if (bccomp($amount, $expected, 2) !== 0) {
            return new GatewayValidationResult(
                success: false,
                transactionId: $trx,
                amount: $amount,
                payload: $payload,
                message: "Amount mismatch: expected {$expected}, got {$amount}.",
            );
        }

        return new GatewayValidationResult(
            success: true,
            transactionId: $trx,
            amount: $expected,
            payload: $payload,
        );
    }
}
