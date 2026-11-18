<?php

namespace App\Services\Payments;

class GatewayInitiation
{
    /**
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $mode, // redirect|form|manual|instant
        public readonly ?string $redirectUrl = null,
        public readonly array $form = [],
        public readonly array $meta = [],
    ) {}
}
