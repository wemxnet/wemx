<?php

namespace Extensions\Modules\Marketplace\Support;

use App\Models\User;
use Extensions\Modules\Marketplace\Enums\ResourceStatus;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Models\MarketplaceCreatorGatewayConfig;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Models\MarketplaceResourceTeamMember;
use Illuminate\Validation\ValidationException;

final class MarketplaceLimits
{
    public static function maxUploadKilobytes(): int
    {
        return max(1, (int) config('marketplace.max_upload_kilobytes', 5120));
    }

    public static function maxIconKilobytes(): int
    {
        return max(1, (int) config('marketplace.max_icon_kilobytes', 2048));
    }

    public static function maxTagsPerResource(): int
    {
        return max(1, (int) config('marketplace.max_tags_per_resource', 10));
    }

    public static function authoredResourceCount(User $user): int
    {
        return MarketplaceResource::query()->where('user_id', $user->id)->count();
    }

    public static function pendingResourceCount(User $user): int
    {
        return MarketplaceResource::query()
            ->where('user_id', $user->id)
            ->where('status', ResourceStatus::Pending)
            ->count();
    }

    public static function maxVersionsFor(MarketplaceResource $resource): int
    {
        if ($resource->version_limit !== null) {
            return max(1, (int) $resource->version_limit);
        }

        $base = max(1, (int) config('marketplace.max_versions_per_resource', 10));
        $milestone = max(1, (int) config('marketplace.version_download_milestone', 500));
        $bonusPerMilestone = max(0, (int) config('marketplace.max_versions_bonus_per_milestone', 1));
        $absolute = max($base, (int) config('marketplace.max_versions_absolute', 25));

        $bonus = (int) floor($resource->downloads_count / $milestone) * $bonusPerMilestone;

        return min($absolute, $base + $bonus);
    }

    public static function bypassesLimits(User $user): bool
    {
        return (bool) config('marketplace.staff_bypass_limits', true)
            && $user->isStaff();
    }

    /**
     * @throws ValidationException
     */
    public static function assertAccountAgeEligible(User $user, string $attribute = 'user_id'): void
    {
        if (self::bypassesLimits($user)) {
            return;
        }

        $days = max(0, (int) config('marketplace.min_account_age_days', 3));

        if ($days === 0) {
            return;
        }

        if (! $user->created_at || $user->created_at->isAfter(now()->subDays($days))) {
            throw ValidationException::withMessages([
                $attribute => sprintf(
                    'Your account must be at least %d day(s) old before publishing marketplace resources.',
                    $days,
                ),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public static function assertCanCreateResource(User $user, string $attribute = 'user_id'): void
    {
        self::assertAccountAgeEligible($user, $attribute);

        if (self::bypassesLimits($user)) {
            return;
        }

        $maxResources = max(1, (int) config('marketplace.max_resources_per_user', 15));

        if (self::authoredResourceCount($user) >= $maxResources) {
            throw ValidationException::withMessages([
                $attribute => sprintf(
                    'You can publish up to %d marketplace resources. Remove or transfer a listing before creating another.',
                    $maxResources,
                ),
            ]);
        }

        $maxPending = max(1, (int) config('marketplace.max_pending_resources_per_user', 5));

        if (self::pendingResourceCount($user) >= $maxPending) {
            throw ValidationException::withMessages([
                $attribute => sprintf(
                    'You already have %d resources awaiting review. Wait for approval before submitting more.',
                    $maxPending,
                ),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public static function assertCanCreateVersion(MarketplaceResource $resource, string $attribute = 'version_id'): void
    {
        $current = $resource->versions()->count();
        $max = self::maxVersionsFor($resource);

        if ($current >= $max) {
            $message = $resource->version_limit !== null
                ? sprintf('This resource can have up to %d version(s).', $max)
                : sprintf('This resource can have up to %d version(s). Popular resources unlock more slots as downloads grow.', $max);

            throw ValidationException::withMessages([
                $attribute => $message,
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public static function assertCanAddTeamMember(MarketplaceResource $resource, string $attribute = 'user_id'): void
    {
        $max = max(1, (int) config('marketplace.max_team_members_per_resource', 5));

        $current = MarketplaceResourceTeamMember::query()
            ->where('resource_id', $resource->id)
            ->where('role', '!=', TeamRole::Owner->value)
            ->count();

        if ($current >= $max) {
            throw ValidationException::withMessages([
                $attribute => sprintf(
                    'This resource can have up to %d collaborators in addition to the owner.',
                    $max,
                ),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public static function assertCanCreateCreatorGateway(User $user, string $attribute = 'user_id'): void
    {
        if (self::bypassesLimits($user)) {
            return;
        }

        $max = max(1, (int) config('marketplace.max_creator_gateways_per_user', 3));
        $current = MarketplaceCreatorGatewayConfig::query()->where('user_id', $user->id)->count();

        if ($current >= $max) {
            throw ValidationException::withMessages([
                $attribute => sprintf(
                    'You can configure up to %d marketplace payment methods.',
                    $max,
                ),
            ]);
        }
    }
}
