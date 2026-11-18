<?php

namespace App\Services\Payments\Drivers;

use App\Models\Order;
use App\Services\Payments\GatewayInitiation;
use App\Services\Payments\GatewayValidationResult;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SslCommerzGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected SettingsService $settings,
        protected array $config = [],
    ) {}

    public function name(): string
    {
        return 'sslcommerz';
    }

    public function initiate(Order $order, array $customer = []): GatewayInitiation
    {
        $storeId = $this->credential('store_id', 'SSLCOMMERZ_STORE_ID');
        $storePassword = $this->credential('store_password', 'SSLCOMMERZ_STORE_PASSWORD');

        $payload = [
            'store_id' => $storeId,
            'store_passwd' => $storePassword,
            'total_amount' => number_format((float) $order->net_amount, 2, '.', ''),
            'currency' => 'BDT',
            'tran_id' => $order->order_no,
            'success_url' => $this->config['success_url'] ?: route('payments.sslcommerz.success'),
            'fail_url' => $this->config['fail_url'] ?: route('payments.sslcommerz.fail'),
            'cancel_url' => $this->config['cancel_url'] ?: route('payments.sslcommerz.cancel'),
            'ipn_url' => $this->config['ipn_url'] ?: route('payments.sslcommerz.ipn'),
            'cus_name' => (string) ($customer['name'] ?? 'Student'),
            'cus_email' => (string) ($customer['email'] ?? 'noreply@example.com'),
            'cus_phone' => (string) ($customer['phone'] ?? '01700000000'),
            'cus_add1' => (string) ($customer['address'] ?? 'N/A'),
            'cus_city' => 'Dhaka',
            'cus_country' => 'Bangladesh',
            'shipping_method' => 'NO',
            'product_name' => (string) ($customer['product_name'] ?? 'Course'),
            'product_category' => 'Education',
            'product_profile' => 'general',
            'value_a' => $order->order_no,
        ];

        $response = Http::asForm()
            ->timeout(30)
            ->post($this->sessionUrl(), $payload);

        $body = $response->json() ?? ['raw' => $response->body()];

        if (! $response->successful() || ($body['status'] ?? null) !== 'SUCCESS' || empty($body['GatewayPageURL'])) {
            Log::warning('SSLCommerz session init failed', ['order' => $order->order_no, 'body' => $body]);

            return new GatewayInitiation(
                mode: 'redirect',
                redirectUrl: route('payments.failed', ['order' => $order->order_no]),
                meta: ['error' => $body['failedreason'] ?? 'Unable to start SSLCommerz session.', 'response' => $body],
            );
        }

        return new GatewayInitiation(
            mode: 'redirect',
            redirectUrl: (string) $body['GatewayPageURL'],
            meta: ['response' => $body],
        );
    }

    public function validateCallback(Order $order, array $payload): GatewayValidationResult
    {
        return $this->validateWithApi($order, $payload);
    }

    public function validateIpn(Order $order, array $payload): GatewayValidationResult
    {
        return $this->validateWithApi($order, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function validateWithApi(Order $order, array $payload): GatewayValidationResult
    {
        $valId = (string) ($payload['val_id'] ?? '');

        if ($valId === '') {
            return new GatewayValidationResult(
                success: false,
                payload: $payload,
                message: 'Missing SSLCommerz val_id.',
            );
        }

        $response = Http::asForm()
            ->timeout(30)
            ->get($this->validationUrl(), [
                'val_id' => $valId,
                'store_id' => $this->credential('store_id', 'SSLCOMMERZ_STORE_ID'),
                'store_passwd' => $this->credential('store_password', 'SSLCOMMERZ_STORE_PASSWORD'),
                'format' => 'json',
            ]);

        $body = $response->json() ?? [];
        $status = strtoupper((string) ($body['status'] ?? ''));
        $amount = number_format((float) ($body['amount'] ?? $body['currency_amount'] ?? 0), 2, '.', '');
        $expected = number_format((float) $order->net_amount, 2, '.', '');
        $tranId = (string) ($body['tran_id'] ?? $payload['tran_id'] ?? '');

        if (! in_array($status, ['VALID', 'VALIDATED'], true)) {
            return new GatewayValidationResult(
                success: false,
                transactionId: $tranId !== '' ? $tranId : null,
                amount: $amount,
                payload: array_merge($payload, ['validation' => $body]),
                message: 'SSLCommerz validation status was not VALID.',
            );
        }

        if ($tranId !== '' && $tranId !== $order->order_no) {
            return new GatewayValidationResult(
                success: false,
                transactionId: $tranId,
                amount: $amount,
                payload: array_merge($payload, ['validation' => $body]),
                message: 'Transaction id does not match order.',
            );
        }

        if (bccomp($amount, $expected, 2) !== 0) {
            return new GatewayValidationResult(
                success: false,
                transactionId: $tranId !== '' ? $tranId : null,
                amount: $amount,
                payload: array_merge($payload, ['validation' => $body]),
                message: "Amount mismatch: expected {$expected}, got {$amount}.",
            );
        }

        return new GatewayValidationResult(
            success: true,
            transactionId: (string) ($body['bank_tran_id'] ?? $valId),
            amount: $expected,
            payload: array_merge($payload, ['validation' => $body]),
        );
    }

    protected function sandbox(): bool
    {
        $fromSettings = $this->settings->get('payments.sslcommerz_sandbox');

        if ($fromSettings !== null) {
            return (bool) $fromSettings;
        }

        return (bool) ($this->config['sandbox'] ?? true);
    }

    protected function sessionUrl(): string
    {
        return $this->sandbox()
            ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
            : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';
    }

    protected function validationUrl(): string
    {
        return $this->sandbox()
            ? 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'
            : 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php';
    }

    protected function credential(string $configKey, string $envKey): string
    {
        $value = $this->config[$configKey] ?? env($envKey);

        return is_string($value) ? $value : '';
    }
}
