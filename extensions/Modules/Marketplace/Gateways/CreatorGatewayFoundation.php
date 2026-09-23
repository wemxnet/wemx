<?php

namespace Extensions\Modules\Marketplace\Gateways;

use Extensions\Modules\Marketplace\Models\MarketplaceCreatorGatewayConfig;
use Extensions\Modules\Marketplace\Models\MarketplaceSale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class CreatorGatewayFoundation
{
    abstract public function id(): string;

    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * @return array<string, array{label: string, description?: string, type: string, rules: list<string>, options?: array<string, string>}>
     */
    abstract public function credentialFields(): array;

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(array $credentials): array
    {
        $rules = [];

        foreach ($this->credentialFields() as $key => $field) {
            $rules[$key] = $field['rules'] ?? ['nullable'];
        }

        return validator($credentials, $rules)->validate();
    }

    abstract public function checkout(MarketplaceSale $sale, MarketplaceCreatorGatewayConfig $config): Response;

    abstract public function handleWebhook(Request $request, MarketplaceCreatorGatewayConfig $config): Response;

    public function handleReturn(Request $request, MarketplaceSale $sale, MarketplaceCreatorGatewayConfig $config): Response
    {
        return redirect($sale->resource->clientUrl());
    }
}
