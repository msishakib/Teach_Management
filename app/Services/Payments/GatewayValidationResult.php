<?php

namespace App\Services\Payments;

class GatewayValidationResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $transactionId = null,
        public readonly ?string $amount = null,
        public readonly array $payload = [],
        public readonly ?string $message = null,
    ) {}
}
