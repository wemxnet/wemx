<?php

namespace Extensions\Modules\Marketplace\Actions\Concerns;

use App\Models\User;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Illuminate\Validation\ValidationException;

trait AuthorizesMarketplaceStaff
{
    protected function user(int $userId): User
    {
        $user = User::find($userId);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'The selected user is invalid.',
            ]);
        }

        return $user;
    }

    protected function staffUser(int $userId, string $permission = 'admin.marketplace.manage'): User
    {
        $user = $this->user($userId);

        if (! $user->isStaff() || ! $user->hasPermission($permission)) {
            throw ValidationException::withMessages([
                'admin_user_id' => 'You do not have permission to manage the marketplace.',
            ]);
        }

        return $user;
    }

    protected function assertCanManageResource(User $user, MarketplaceResource $resource, TeamRole $minimum = TeamRole::Developer): void
    {
        if ($resource->userCan($user, $minimum)) {
            return;
        }

        throw ValidationException::withMessages([
            'resource_id' => 'You do not have permission to manage this resource.',
        ]);
    }
}
