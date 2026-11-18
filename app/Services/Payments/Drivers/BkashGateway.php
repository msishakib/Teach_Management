<?php

namespace App\Services\Payments\Drivers;

use App\Models\Order;
use App\Services\Payments\GatewayInitiation;
use App\Services\Payments\GatewayValidationResult;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * bKash tokenized checkout (create → redirect → execute / query).
 */
class BkashGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected SettingsService $settings,
        protected array $config = [],
    ) {}

    public function name(): string
    {
        return 'bkash';
    }

    public function initiate(Order $order, array $customer = []): GatewayInitiation
    {
        $token = $this->grantToken();

        if ($token === null) {
            return new GatewayInitiation(
                mode: 'redirect',
                redirectUrl: route('payments.failed', ['order' => $order->order_no]),
                meta: ['error' => 'Unable to obtain bKash token.'],
            );
        }

        $payload = [
            'mode' => '0011',
            'payerReference' => (string) ($customer['phone'] ?? $order->order_no),
            'callbackURL' => $this->config['callback_url'] ?: route('payments.bkash.callback'),
            'amount' => number_format((float) $order->net_amount, 2, '.', ''),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $order->order_no,
        ];

        $response = Http::withHeaders($this->authHeaders($token))
            ->timeout(30)
            ->post($this->baseUrl().'/tokenized/checkout/create', $payload);

        $body = $response->json() ?? [];

        if (! $response->successful() || empty($body['bkashURL']) || empty($body['paymentID'])) {
            Log::warning('bKash create payment failed', ['order' => $order->order_no, 'body' => $body]);

            return new GatewayInitiation(
                mode: 'redirect',
                redirectUrl: route('payments.failed', ['order' => $order->order_no]),
                meta: ['error' => $body['statusMessage'] ?? 'Unable to start bKash payment.', 'response' => $body],
            );
        }

        return new GatewayInitiation(
            mode: 'redirect',
            redirectUrl: (string) $body['bkashURL'],
            meta: [
                'payment_id' => $body['paymentID'],
                'response' => $body,
            ],
        );
    }

    public function validateCallback(Order $order, array $payload): GatewayValidationResult
    {
        $paymentId = (string) ($payload['paymentID'] ?? $payload['payment_id'] ?? '');

        if ($paymentId === '') {
            return new GatewayValidationResult(
                success: false,
                payload: $payload,
                message: 'Missing bKash paymentID.',
            );
        }

        $status = strtolower((string) ($payload['status'] ?? 'success'));

        if (in_array($status, ['failure', 'failed', 'cancel', 'cancelled'], true)) {
            return new GatewayValidationResult(
                success: false,
                payload: $payload,
                message: 'bKash payment was cancelled or failed.',
            );
        }

        $token = $this->grantToken();

        if ($token === null) {
            return new GatewayValidationResult(
                success: false,
                payload: $payload,
                message: 'Unable to obtain bKash token for execute.',
            );
        }

        $response = Http::withHeaders($this->authHeaders($token))
            ->timeout(30)
            ->post($this->baseUrl().'/tokenized/checkout/execute', [
                'paymentID' => $paymentId,
            ]);

        $body = $response->json() ?? [];

        return $this->interpretQueryBody($order, $payload, $body);
    }

    public function validateIpn(Order $order, array $payload): GatewayValidationResult
    {
        $paymentId = (string) ($payload['paymentID'] ?? $payload['payment_id'] ?? '');

        if ($paymentId === '') {
            return new GatewayValidationResult(
                success: false,
                payload: $payload,
                message: 'Missing bKash paymentID.',
            );
        }

        $token = $this->grantToken();

        if ($token === null) {
            return new GatewayValidationResult(
                success: false,
                payload: $payload,
                message: 'Unable to obtain bKash token for query.',
            );
        }

        $response = Http::withHeaders($this->authHeaders($token))
            ->timeout(30)
            ->post($this->baseUrl().'/tokenized/checkout/payment/status', [
                'paymentID' => $paymentId,
            ]);

        $body = $response->json() ?? [];

        return $this->interpretQueryBody($order, $payload, $body);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $body
     */
    protected function interpretQueryBody(Order $order, array $payload, array $body): GatewayValidationResult
    {
        $txnStatus = strtoupper((string) ($body['transactionStatus'] ?? $body['statusCode'] ?? ''));
        $amount = number_format((float) ($body['amount'] ?? 0), 2, '.', '');
        $expected = number_format((float) $order->net_amount, 2, '.', '');
        $trxId = (string) ($body['trxID'] ?? $body['paymentID'] ?? '');
        $invoice = (string) ($body['merchantInvoiceNumber'] ?? '');

        $ok = in_array($txnStatus, ['COMPLETED', '0000', 'SUCCESS'], true)
            || strtoupper((string) ($body['statusMessage'] ?? '')) === 'SUCCESSFUL';

        if (! $ok) {
            return new GatewayValidationResult(
                success: false,
                transactionId: $trxId !== '' ? $trxId : null,
                amount: $amount,
                payload: array_merge($payload, ['query' => $body]),
                message: 'bKash payment was not completed.',
            );
        }

        if ($invoice !== '' && $invoice !== $order->order_no) {
            return new GatewayValidationResult(
                success: false,
                transactionId: $trxId !== '' ? $trxId : null,
                amount: $amount,
                payload: array_merge($payload, ['query' => $body]),
                message: 'Merchant invoice does not match order.',
            );
        }

        if (bccomp($amount, $expected, 2) !== 0) {
            return new GatewayValidationResult(
                success: false,
                transactionId: $trxId !== '' ? $trxId : null,
                amount: $amount,
                payload: array_merge($payload, ['query' => $body]),
                message: "Amount mismatch: expected {$expected}, got {$amount}.",
            );
        }

        return new GatewayValidationResult(
            success: true,
            transactionId: $trxId !== '' ? $trxId : $order->order_no,
            amount: $expected,
            payload: array_merge($payload, ['query' => $body]),
        );
    }

    protected function grantToken(): ?string
    {
        $cacheKey = 'payments.bkash.token.'.($this->sandbox() ? 'sandbox' : 'live');

        return Cache::remember($cacheKey, now()->addMinutes(50), function (): ?string {
            $response = Http::withHeaders([
                'username' => $this->credential('username', 'BKASH_USERNAME'),
                'password' => $this->credential('password', 'BKASH_PASSWORD'),
            ])->timeout(30)->post($this->baseUrl().'/tokenized/checkout/token/grant', [
                'app_key' => $this->credential('app_key', 'BKASH_APP_KEY'),
                'app_secret' => $this->credential('app_secret', 'BKASH_APP_SECRET'),
            ]);

            $body = $response->json() ?? [];

            return is_string($body['id_token'] ?? null) ? $body['id_token'] : null;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function authHeaders(string $token): array
    {
        return [
            'Authorization' => $token,
            'X-APP-Key' => $this->credential('app_key', 'BKASH_APP_KEY'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    protected function sandbox(): bool
    {
        $fromSettings = $this->settings->get('payments.bkash_sandbox');

        if ($fromSettings !== null) {
            return (bool) $fromSettings;
        }

        return (bool) ($this->config['sandbox'] ?? true);
    }

    protected function baseUrl(): string
    {
        return $this->sandbox()
            ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'
            : 'https://tokenized.pay.bka.sh/v1.2.0-beta';
    }

    protected function credential(string $configKey, string $envKey): string
    {
        $value = $this->config[$configKey] ?? env($envKey);

        return is_string($value) ? $value : '';
    }
}
