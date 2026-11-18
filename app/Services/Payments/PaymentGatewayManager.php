<?php

namespace App\Services\Payments;

use App\Enums\PaymentGateway;
use App\Services\Payments\Drivers\BkashGateway;
use App\Services\Payments\Drivers\FakeGateway;
use App\Services\Payments\Drivers\ManualGateway;
use App\Services\Payments\Drivers\SslCommerzGateway;
use App\Services\SettingsService;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    public function defaultDriver(): string
    {
        $fromSettings = $this->settings->get('payments.default_gateway');

        if (is_string($fromSettings) && $fromSettings !== '') {
            return $fromSettings;
        }

        return (string) config('payments.default', 'fake');
    }

    public function driver(?string $name = null): PaymentGatewayInterface
    {
        $name ??= $this->defaultDriver();
        $gateway = PaymentGateway::tryFrom($name);

        if ($gateway === null) {
            throw new InvalidArgumentException("Unknown payment gateway [{$name}].");
        }

        if ($gateway === PaymentGateway::Fake && app()->environment('production')) {
            throw new InvalidArgumentException('The fake payment gateway cannot be used in production.');
        }

        $config = (array) config("payments.drivers.{$gateway->value}", []);

        return match ($gateway) {
            PaymentGateway::Fake => new FakeGateway,
            PaymentGateway::Manual => new ManualGateway($this->settings, $config),
            PaymentGateway::SslCommerz => new SslCommerzGateway($this->settings, $config),
            PaymentGateway::Bkash => new BkashGateway($this->settings, $config),
        };
    }
}
