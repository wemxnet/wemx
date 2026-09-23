<?php

namespace Extensions\Modules\Marketplace\Gateways;

use Extensions\Modules\Marketplace\Gateways\PayPalIpn\PayPalIpnGateway;
use Extensions\Modules\Marketplace\Gateways\Stripe\StripeGateway;
use InvalidArgumentException;

class CreatorGatewayRegistry
{
    /**
     * @return array<string, class-string<CreatorGatewayFoundation>>
     */
    public static function drivers(): array
    {
        return [
            'stripe' => StripeGateway::class,
            'paypal_ipn' => PayPalIpnGateway::class,
        ];
    }

    public static function make(string $driver): CreatorGatewayFoundation
    {
        $class = static::drivers()[$driver] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("Unknown marketplace gateway [{$driver}].");
        }

        return new $class;
    }

    public static function label(string $driver): string
    {
        return static::exists($driver) ? static::make($driver)->name() : $driver;
    }

    public static function exists(string $driver): bool
    {
        return array_key_exists($driver, static::drivers());
    }

    /**
     * @return list<array{id: string, name: string, description: string}>
     */
    public static function options(): array
    {
        return collect(static::drivers())
            ->map(function (string $class, string $id) {
                $gateway = new $class;

                return [
                    'id' => $id,
                    'name' => $gateway->name(),
                    'description' => $gateway->description(),
                ];
            })
            ->values()
            ->all();
    }
}
