<?php

namespace Extensions\Modules\Marketplace\Actions;

use App\Actions\Action;
use Extensions\Modules\Marketplace\Actions\Concerns\AuthorizesMarketplaceStaff;
use Extensions\Modules\Marketplace\Enums\LicenseStatus;
use Extensions\Modules\Marketplace\Enums\ResourceStatus;
use Extensions\Modules\Marketplace\Enums\SaleStatus;
use Extensions\Modules\Marketplace\Models\MarketplaceCreatorGatewayConfig;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Models\MarketplaceSale;
use Extensions\Modules\Marketplace\Support\MarketplaceNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MarketplaceSaleActions extends Action
{
    use AuthorizesMarketplaceStaff;

    public function startCheckout(array $input): MarketplaceSale
    {
        $validated = Validator::make($input, [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'resource_id' => ['required', 'integer', 'exists:marketplace_resources,id'],
            'gateway_config_id' => ['nullable', 'integer', 'exists:marketplace_creator_gateway_configs,id'],
        ])->validate();

        $buyer = $this->user((int) $validated['user_id']);
        $resource = MarketplaceResource::query()
            ->with(['gatewayConfigs' => fn ($query) => $query->enabled()->orderBy('name')])
            ->findOrFail($validated['resource_id']);

        if ($resource->status !== ResourceStatus::Approved) {
            throw ValidationException::withMessages([
                'resource_id' => 'This resource is not available for purchase.',
            ]);
        }

        if ($resource->is_disabled) {
            throw ValidationException::withMessages([
                'resource_id' => 'This resource is no longer available for purchase.',
            ]);
        }

        if ($resource->isFree()) {
            throw ValidationException::withMessages([
                'resource_id' => 'This resource is free and does not need to be purchased.',
            ]);
        }

        if ((int) $resource->user_id === (int) $buyer->id) {
            throw ValidationException::withMessages([
                'resource_id' => 'You already own this resource.',
            ]);
        }

        $existingLicense = MarketplaceLicense::query()
            ->where('resource_id', $resource->id)
            ->where('user_id', $buyer->id)
            ->where('status', LicenseStatus::Active)
            ->exists();

        if ($existingLicense) {
            throw ValidationException::withMessages([
                'resource_id' => 'You already have an active license for this resource.',
            ]);
        }

        $gateway = $this->resolveCheckoutGateway($resource, $validated['gateway_config_id'] ?? null);

        $pending = MarketplaceSale::query()
            ->where('resource_id', $resource->id)
            ->where('buyer_id', $buyer->id)
            ->where('status', SaleStatus::Pending)
            ->latest('id')
            ->first();

        if ($pending) {
            $pending->update([
                'gateway_config_id' => $gateway->id,
                'driver' => $gateway->driver,
                'amount' => $resource->price,
                'currency' => $resource->currency,
                'version_id' => $resource->latestApprovedVersion()?->id ?? $pending->version_id,
            ]);

            return $pending->fresh();
        }

        return MarketplaceSale::create([
            'resource_id' => $resource->id,
            'version_id' => $resource->latestApprovedVersion()?->id,
            'seller_id' => $resource->user_id,
            'buyer_id' => $buyer->id,
            'gateway_config_id' => $gateway->id,
            'driver' => $gateway->driver,
            'amount' => $resource->price,
            'currency' => $resource->currency,
            'status' => SaleStatus::Pending,
        ]);
    }

    public function complete(MarketplaceSale $sale, array $input = []): MarketplaceSale
    {
        return DB::transaction(function () use ($sale, $input) {
            $sale = MarketplaceSale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($sale->isCompleted()) {
                return $sale;
            }

            $sale->update(self::omitNullValues([
                'status' => SaleStatus::Completed,
                'gateway_reference' => $input['gateway_reference'] ?? $sale->gateway_reference,
                'gateway_payload' => $input['gateway_payload'] ?? $sale->gateway_payload,
                'paid_at' => now(),
            ]));

            MarketplaceLicense::actions()->issueForSale($sale->fresh());

            $sale->resource->increment('purchases_count');

            $completed = $sale->fresh(['buyer', 'seller', 'resource.category', 'license']);
            MarketplaceNotifier::purchaseCompleted($completed);

            return $completed;
        });
    }

    public function fail(MarketplaceSale $sale): MarketplaceSale
    {
        if ($sale->isCompleted()) {
            return $sale;
        }

        $sale->update(['status' => SaleStatus::Failed]);

        return $sale->fresh();
    }

    protected function resolveCheckoutGateway(MarketplaceResource $resource, mixed $gatewayConfigId): MarketplaceCreatorGatewayConfig
    {
        $enabledGateways = $resource->relationLoaded('gatewayConfigs')
            ? $resource->gatewayConfigs->where('is_enabled', true)->values()
            : $resource->gatewayConfigs()->enabled()->orderBy('name')->get();

        if ($enabledGateways->isEmpty()) {
            throw ValidationException::withMessages([
                'resource_id' => 'The creator has not configured a payment method yet.',
            ]);
        }

        if ($gatewayConfigId === null || $gatewayConfigId === '') {
            if ($enabledGateways->count() === 1) {
                return $enabledGateways->first();
            }

            throw ValidationException::withMessages([
                'gateway_config_id' => 'Select a payment method to continue checkout.',
            ]);
        }

        $gateway = $enabledGateways->firstWhere('id', (int) $gatewayConfigId);

        if (! $gateway) {
            throw ValidationException::withMessages([
                'gateway_config_id' => 'That payment method is not available for this resource.',
            ]);
        }

        return $gateway;
    }
}
