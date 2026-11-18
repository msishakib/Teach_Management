<?php

namespace App\Services\Payments;

use App\Models\Order;

interface PaymentGatewayInterface
{
    public function name(): string;

    /**
     * @param  array<string, mixed>  $customer
     */
    public function initiate(Order $order, array $customer = []): GatewayInitiation;

    /**
     * Server-side validation of a browser callback / return payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function validateCallback(Order $order, array $payload): GatewayValidationResult;

    /**
     * Server-side validation of an IPN / webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function validateIpn(Order $order, array $payload): GatewayValidationResult;
}
