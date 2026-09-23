<?php

namespace Extensions\Modules\Marketplace\Actions;

use App\Actions\Action;
use Extensions\Modules\Marketplace\Actions\Concerns\AuthorizesMarketplaceStaff;
use Extensions\Modules\Marketplace\Enums\LicenseStatus;
use Extensions\Modules\Marketplace\Enums\ResourceStatus;
use Extensions\Modules\Marketplace\Enums\SaleStatus;
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
        ])->validate();

        $buyer = $this->user((int) $validated['user_id']);
        $resource = MarketplaceResource::query()->with('gatewayConfig')->findOrFail($validated['resource_id']);

        if ($resource->status !== ResourceStatus::Approved) {
            throw ValidationException::withMessages([
                'resource_id' => 'This resource is not available for purchase.',
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

        if (! $resource->gatewayConfig || ! $resource->gatewayConfig->is_enabled) {
            throw ValidationException::withMessages([
                'resource_id' => 'The creator has not configured a payment method yet.',
            ]);
        }

        $pending = MarketplaceSale::query()
            ->where('resource_id', $resource->id)
            ->where('buyer_id', $buyer->id)
            ->where('status', SaleStatus::Pending)
            ->latest('id')
            ->first();

        if ($pending) {
            return $pending;
        }

        return MarketplaceSale::create([
            'resource_id' => $resource->id,
            'version_id' => $resource->latestApprovedVersion()?->id,
            'seller_id' => $resource->user_id,
            'buyer_id' => $buyer->id,
            'gateway_config_id' => $resource->gateway_config_id,
            'driver' => $resource->gatewayConfig->driver,
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
}
