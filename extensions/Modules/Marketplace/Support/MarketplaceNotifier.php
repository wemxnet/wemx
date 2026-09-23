<?php

namespace Extensions\Modules\Marketplace\Support;

use App\Models\User;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Models\MarketplaceResourceTeamMember;
use Extensions\Modules\Marketplace\Models\MarketplaceResourceVersion;
use Extensions\Modules\Marketplace\Models\MarketplaceSale;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MarketplaceNotifier
{
    public static function resourcePending(MarketplaceResource $resource): void
    {
        static::notifyManagers($resource, 'marketplace.resource.pending', [
            'resource_name' => $resource->name,
            'category' => $resource->category?->name ?? '',
            'status' => $resource->status->label(),
        ], $resource->studioUrl('versions'));
    }

    public static function resourceApproved(MarketplaceResource $resource): void
    {
        static::notifyManagers($resource, 'marketplace.resource.approved', [
            'resource_name' => $resource->name,
            'category' => $resource->category?->name ?? '',
        ], $resource->clientUrl());
    }

    public static function resourceRejected(MarketplaceResource $resource): void
    {
        static::notifyManagers($resource, 'marketplace.resource.rejected', [
            'resource_name' => $resource->name,
            'category' => $resource->category?->name ?? '',
            'rejection_reason' => static::reason($resource->rejection_reason),
        ], $resource->studioUrl());
    }

    public static function resourceSuspended(MarketplaceResource $resource): void
    {
        static::notifyManagers($resource, 'marketplace.resource.suspended', [
            'resource_name' => $resource->name,
            'category' => $resource->category?->name ?? '',
            'rejection_reason' => static::reason($resource->rejection_reason, 'No reason was provided.'),
        ], $resource->studioUrl());
    }

    public static function resourceFeatured(MarketplaceResource $resource): void
    {
        if (! $resource->is_featured) {
            return;
        }

        static::notifyManagers($resource, 'marketplace.resource.featured', [
            'resource_name' => $resource->name,
            'category' => $resource->category?->name ?? '',
        ], $resource->clientUrl());
    }

    public static function versionSubmitted(MarketplaceResourceVersion $version): void
    {
        $resource = $version->resource()->with('category')->first() ?? $version->resource;

        if (! $resource) {
            return;
        }

        static::notifyManagers($resource, 'marketplace.version.submitted', [
            'resource_name' => $resource->name,
            'version_name' => $version->name,
            'version_number' => $version->version,
        ], $resource->studioUrl('versions'), (string) $version->id);
    }

    public static function versionApproved(MarketplaceResourceVersion $version): void
    {
        $resource = $version->resource()->with('category')->first() ?? $version->resource;

        if (! $resource) {
            return;
        }

        static::notifyManagers($resource, 'marketplace.version.approved', [
            'resource_name' => $resource->name,
            'version_name' => $version->name,
            'version_number' => $version->version,
        ], $resource->clientUrl(), (string) $version->id);
    }

    public static function versionRejected(MarketplaceResourceVersion $version): void
    {
        $resource = $version->resource()->with('category')->first() ?? $version->resource;

        if (! $resource) {
            return;
        }

        static::notifyManagers($resource, 'marketplace.version.rejected', [
            'resource_name' => $resource->name,
            'version_name' => $version->name,
            'version_number' => $version->version,
        ], $resource->studioUrl('versions'), (string) $version->id);
    }

    public static function versionReleased(MarketplaceResourceVersion $version): void
    {
        $resource = $version->resource()->with('category')->first() ?? $version->resource;

        if (! $resource) {
            return;
        }

        $preview = trim((string) $version->changelog);
        $preview = $preview === '' ? 'A new version is available to download.' : Str::limit($preview, 240);

        MarketplaceLicense::query()
            ->with('user')
            ->where('resource_id', $resource->id)
            ->active()
            ->each(function (MarketplaceLicense $license) use ($resource, $version, $preview): void {
                if (! $license->user) {
                    return;
                }

                static::sendUser($license->user, 'marketplace.version.released', [
                    'resource_name' => $resource->name,
                    'version_name' => $version->name,
                    'version_number' => $version->version,
                    'changelog' => $preview,
                ], $resource->clientUrl(), 'marketplace.version.released.'.$version->id.'.'.$license->user_id);
            });
    }

    public static function purchaseCompleted(MarketplaceSale $sale): void
    {
        $sale->loadMissing(['buyer', 'seller', 'resource.category', 'license']);

        $resource = $sale->resource;
        $amount = $sale->formattedAmount();

        if ($sale->buyer) {
            static::sendUser($sale->buyer, 'marketplace.purchase.buyer', [
                'resource_name' => $resource->name,
                'amount' => $amount,
                'license_key' => $sale->license?->license_key ?? '',
                'seller_name' => $sale->seller?->username ?? ($sale->seller?->full_name ?? 'the creator'),
            ], $resource->clientUrl(), 'marketplace.sale.'.$sale->id.'.buyer');
        }

        if ($sale->seller) {
            static::sendUser($sale->seller, 'marketplace.purchase.seller', [
                'resource_name' => $resource->name,
                'amount' => $amount,
                'buyer_name' => $sale->buyer?->username ?? ($sale->buyer?->full_name ?? 'a customer'),
            ], $resource->studioUrl('licenses'), 'marketplace.sale.'.$sale->id.'.seller');
        }
    }

    public static function licenseGranted(MarketplaceLicense $license): void
    {
        $license->loadMissing(['user', 'resource.category']);

        if (! $license->user || ! $license->resource) {
            return;
        }

        static::sendUser($license->user, 'marketplace.license.granted', [
            'resource_name' => $license->resource->name,
            'license_key' => $license->license_key,
        ], $license->resource->clientUrl(), 'marketplace.license.'.$license->id.'.granted');
    }

    public static function licenseRevoked(MarketplaceLicense $license): void
    {
        $license->loadMissing(['user', 'resource.category']);

        if (! $license->user || ! $license->resource) {
            return;
        }

        static::sendUser($license->user, 'marketplace.license.revoked', [
            'resource_name' => $license->resource->name,
            'license_key' => $license->license_key,
        ], $license->resource->clientUrl(), 'marketplace.license.'.$license->id.'.revoked');
    }

    public static function teamMemberInvited(MarketplaceResource $resource, MarketplaceResourceTeamMember $member, ?User $actor = null): void
    {
        $member->loadMissing('user');
        $resource->loadMissing('category');

        if (! $member->user) {
            return;
        }

        $inviter = $actor
            ? ($actor->full_name ?: $actor->username)
            : 'A marketplace creator';

        static::sendUser($member->user, 'marketplace.team.invited', [
            'resource_name' => $resource->name,
            'role' => $member->role->label(),
            'inviter_name' => $inviter,
        ], $resource->studioUrl(), 'marketplace.team.'.$resource->id.'.'.$member->user_id.'.invited');
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    protected static function notifyManagers(
        MarketplaceResource $resource,
        string $template,
        array $variables,
        string $buttonUrl,
        ?string $suffix = null,
    ): void {
        foreach (static::managerRecipients($resource) as $user) {
            $identifier = $template.'.'.$resource->id.'.'.$user->id;

            if ($suffix !== null && $suffix !== '') {
                $identifier .= '.'.$suffix;
            }

            static::sendUser($user, $template, $variables, $buttonUrl, $identifier);
        }
    }

    /**
     * @return Collection<int, User>
     */
    protected static function managerRecipients(MarketplaceResource $resource): Collection
    {
        $resource->loadMissing(['author', 'teamMembers.user', 'category']);

        $users = collect();

        if ($resource->author) {
            $users->push($resource->author);
        }

        foreach ($resource->teamMembers as $member) {
            if (! $member->user) {
                continue;
            }

            if (! in_array($member->role, [TeamRole::Owner, TeamRole::Manager], true)) {
                continue;
            }

            $users->push($member->user);
        }

        return $users->unique('id')->values();
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    protected static function sendUser(User $user, string $template, array $variables, string $buttonUrl, string $identifier): void
    {
        $user->email([
            'template' => $template,
            'identifier' => $identifier,
            'variables' => $variables,
            'mailable_type' => MarketplaceResource::class,
            'button' => [
                'url' => $buttonUrl,
            ],
        ]);
    }

    protected static function reason(?string $reason, string $fallback = 'No reason was provided.'): string
    {
        $reason = trim((string) $reason);

        return $reason !== '' ? $reason : $fallback;
    }
}
