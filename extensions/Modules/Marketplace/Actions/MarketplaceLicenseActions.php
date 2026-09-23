<?php

namespace Extensions\Modules\Marketplace\Actions;

use App\Actions\Action;
use App\Models\User;
use Extensions\Modules\Marketplace\Actions\Concerns\AuthorizesMarketplaceStaff;
use Extensions\Modules\Marketplace\Enums\LicenseStatus;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Models\MarketplaceSale;
use Extensions\Modules\Marketplace\Support\MarketplaceNotifier;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MarketplaceLicenseActions extends Action
{
    use AuthorizesMarketplaceStaff;

    public function issueForSale(MarketplaceSale $sale): MarketplaceLicense
    {
        $existing = MarketplaceLicense::query()->where('sale_id', $sale->id)->first();

        if ($existing) {
            return $existing;
        }

        $existingForUser = MarketplaceLicense::query()
            ->where('resource_id', $sale->resource_id)
            ->where('user_id', $sale->buyer_id)
            ->first();

        $payload = [
            'sale_id' => $sale->id,
            'status' => LicenseStatus::Active,
            'source' => 'purchase',
            'payment_method' => $sale->driver,
            'transaction_id' => $sale->gateway_reference,
            'purchased_at' => $sale->paid_at ?? now(),
            'max_activations' => 1,
        ];

        if ($existingForUser) {
            $existingForUser->update($payload);

            return $existingForUser->fresh();
        }

        return MarketplaceLicense::create([
            ...$payload,
            'resource_id' => $sale->resource_id,
            'user_id' => $sale->buyer_id,
            'license_key' => MarketplaceLicense::generateKey(),
        ]);
    }

    public function grantFreeLicense(MarketplaceResource $resource, User $user): MarketplaceLicense
    {
        $existing = MarketplaceLicense::query()
            ->where('resource_id', $resource->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $license = MarketplaceLicense::create([
            'resource_id' => $resource->id,
            'user_id' => $user->id,
            'license_key' => MarketplaceLicense::generateKey(),
            'status' => LicenseStatus::Active,
            'source' => 'free',
            'purchased_at' => now(),
            'max_activations' => 1,
        ]);

        MarketplaceNotifier::licenseGranted($license);

        return $license;
    }

    public function grantAsManager(array $input): MarketplaceLicense
    {
        $validated = Validator::make($input, [
            'actor_user_id' => ['required', 'integer', 'exists:users,id'],
            'resource_id' => ['required', 'integer', 'exists:marketplace_resources,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:username'],
            'username' => ['nullable', 'string', 'max:255', 'required_without:user_id'],
            'payment_method' => ['nullable', 'string', 'max:80'],
            'transaction_id' => ['nullable', 'string', 'max:255'],
            'purchased_at' => ['nullable', 'date'],
            'notify' => ['sometimes', 'boolean'],
        ])->validate();

        $actor = $this->user((int) $validated['actor_user_id']);
        $resource = MarketplaceResource::findOrFail($validated['resource_id']);
        $this->assertCanManageResource($actor, $resource, TeamRole::Support);

        $user = isset($validated['user_id'])
            ? $this->user((int) $validated['user_id'])
            : User::query()
                ->where(function ($query) use ($validated): void {
                    $query->where('username', $validated['username'])
                        ->orWhere('email', $validated['username']);
                })
                ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'username' => 'No user matches that username or email.',
            ]);
        }

        $existing = MarketplaceLicense::query()
            ->where('resource_id', $resource->id)
            ->where('user_id', $user->id)
            ->first();

        $payload = [
            'status' => LicenseStatus::Active,
            'source' => 'manual',
            'payment_method' => $validated['payment_method'] ?? $existing?->payment_method,
            'transaction_id' => $validated['transaction_id'] ?? $existing?->transaction_id,
            'purchased_at' => $validated['purchased_at'] ?? $existing?->purchased_at ?? now(),
            'granted_by' => $actor->id,
        ];

        if ($existing) {
            $wasActive = $existing->status === LicenseStatus::Active;
            $existing->update($payload);
            $license = $existing->fresh(['user', 'resource']);

            if (! $wasActive) {
                $resource->increment('purchases_count');
            }

            if ($validated['notify'] ?? true) {
                MarketplaceNotifier::licenseGranted($license);
            }

            return $license;
        }

        $license = MarketplaceLicense::create([
            ...$payload,
            'resource_id' => $resource->id,
            'user_id' => $user->id,
            'license_key' => MarketplaceLicense::generateKey(),
            'max_activations' => 1,
        ]);

        $resource->increment('purchases_count');

        if ($validated['notify'] ?? true) {
            MarketplaceNotifier::licenseGranted($license->fresh(['user', 'resource']));
        }

        return $license->fresh(['user', 'resource']);
    }

    public function updateAsManager(array $input): MarketplaceLicense
    {
        $validated = Validator::make($input, [
            'actor_user_id' => ['required', 'integer', 'exists:users,id'],
            'license_id' => ['required', 'integer', 'exists:marketplace_licenses,id'],
            'status' => ['sometimes', Rule::enum(LicenseStatus::class)],
            'domain' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:80'],
            'transaction_id' => ['nullable', 'string', 'max:255'],
            'purchased_at' => ['nullable', 'date'],
            'max_activations' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'expires_at' => ['nullable', 'date'],
        ])->validate();

        $actor = $this->user((int) $validated['actor_user_id']);
        $license = MarketplaceLicense::query()->with('resource')->findOrFail($validated['license_id']);
        $this->assertCanManageResource($actor, $license->resource, TeamRole::Support);

        $payload = [];
        $previousStatus = $license->status;

        foreach (['status', 'domain', 'payment_method', 'transaction_id', 'purchased_at', 'max_activations', 'expires_at'] as $key) {
            if (array_key_exists($key, $validated)) {
                $payload[$key] = $validated[$key];
            }
        }

        $license->update($payload);
        $license = $license->fresh(['user', 'resource']);

        if (
            isset($payload['status'])
            && $previousStatus !== LicenseStatus::Revoked
            && $license->status === LicenseStatus::Revoked
        ) {
            MarketplaceNotifier::licenseRevoked($license);
        }

        return $license;
    }

    public function revoke(array $input): MarketplaceLicense
    {
        return $this->updateAsManager([
            ...$input,
            'status' => LicenseStatus::Revoked->value,
        ]);
    }

    public function findUsable(string $licenseKey, MarketplaceResource $resource): MarketplaceLicense
    {
        $license = MarketplaceLicense::query()
            ->where('resource_id', $resource->id)
            ->where('license_key', $licenseKey)
            ->first();

        if (! $license || ! $license->isUsable()) {
            throw ValidationException::withMessages([
                'license_key' => 'This license is not valid.',
            ]);
        }

        return $license;
    }
}
