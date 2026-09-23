<?php

namespace Extensions\Modules\Marketplace\Actions;

use App\Actions\Action;
use App\Models\User;
use Extensions\Modules\Marketplace\Actions\Concerns\AuthorizesMarketplaceStaff;
use Extensions\Modules\Marketplace\Enums\ResourceStatus;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Enums\VersionStatus;
use Extensions\Modules\Marketplace\Models\MarketplaceCategory;
use Extensions\Modules\Marketplace\Models\MarketplaceCreatorGatewayConfig;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Models\MarketplaceResourceTeamMember;
use Extensions\Modules\Marketplace\Support\MarketplaceNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MarketplaceResourceActions extends Action
{
    use AuthorizesMarketplaceStaff;

    public const MAX_ICON_KILOBYTES = 2048;

    public function createAsCreator(array $input): MarketplaceResource
    {
        $validated = Validator::make($input, $this->rules())->validate();

        $user = $this->user((int) $validated['user_id']);
        $category = MarketplaceCategory::query()->visible()->findOrFail($validated['category_id']);

        $icon = $this->storeIcon($validated['icon'] ?? null);

        $resource = MarketplaceResource::create(self::omitNullValues([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'gateway_config_id' => $validated['gateway_config_id'] ?? null,
            'name' => $validated['name'],
            'slug' => MarketplaceResource::generateSlug($validated['slug'] ?? $validated['name']),
            'short_description' => $validated['short_description'],
            'description' => $validated['description'],
            'icon_disk' => $icon['disk'] ?? null,
            'icon_path' => $icon['path'] ?? null,
            'website_url' => $validated['website_url'] ?? null,
            'docs_url' => $validated['docs_url'] ?? null,
            'source_url' => $validated['source_url'] ?? null,
            'support_url' => $validated['support_url'] ?? null,
            'price' => $validated['price'] ?? 0,
            'currency' => $validated['currency'] ?? (function_exists('baseCurrency') ? baseCurrency() : 'USD'),
            'license_type' => $validated['license_type'] ?? 'proprietary',
            'tags' => MarketplaceResource::normalizeTags($validated['tags'] ?? null),
            'available_on_integrated_marketplace' => $validated['available_on_integrated_marketplace'] ?? true,
            'status' => ResourceStatus::Pending,
        ]));

        MarketplaceResourceTeamMember::query()->create([
            'resource_id' => $resource->id,
            'user_id' => $user->id,
            'role' => TeamRole::Owner,
            'invited_by' => $user->id,
        ]);

        $resource = $resource->fresh(['category', 'author', 'teamMembers.user']);
        MarketplaceNotifier::resourcePending($resource);

        return $resource;
    }

    public function updateAsCreator(array $input): MarketplaceResource
    {
        $validated = Validator::make($input, array_merge($this->rules(updating: true), [
            'resource_id' => ['required', 'integer', 'exists:marketplace_resources,id'],
        ]))->validate();

        $user = $this->user((int) $validated['user_id']);
        $resource = MarketplaceResource::findOrFail($validated['resource_id']);
        $this->assertCanManageResource($user, $resource, TeamRole::Manager);

        if (isset($validated['category_id'])) {
            MarketplaceCategory::query()->visible()->findOrFail($validated['category_id']);
        }

        if (isset($validated['gateway_config_id'])) {
            $this->assertGatewayBelongsToOwner($resource, (int) $validated['gateway_config_id']);
        }

        $payload = [];

        foreach ([
            'category_id',
            'gateway_config_id',
            'name',
            'short_description',
            'description',
            'website_url',
            'docs_url',
            'source_url',
            'support_url',
            'price',
            'currency',
            'license_type',
            'available_on_integrated_marketplace',
        ] as $key) {
            if (array_key_exists($key, $validated)) {
                $payload[$key] = $validated[$key];
            }
        }

        if (isset($validated['name']) || isset($validated['slug'])) {
            $payload['slug'] = MarketplaceResource::generateSlug(
                $validated['slug'] ?? $validated['name'] ?? $resource->name,
                $resource->id,
            );
        }

        if (array_key_exists('tags', $validated)) {
            $payload['tags'] = MarketplaceResource::normalizeTags($validated['tags']);
        }

        if (isset($validated['icon'])) {
            $icon = $this->storeIcon($validated['icon']);
            $this->deleteIcon($resource);
            $payload['icon_disk'] = $icon['disk'];
            $payload['icon_path'] = $icon['path'];
        }

        $resubmitted = false;

        if ($resource->status === ResourceStatus::Rejected) {
            $payload['status'] = ResourceStatus::Pending;
            $payload['rejection_reason'] = null;
            $resubmitted = true;
        }

        $resource->update(self::omitNullValues($payload));
        $resource = $resource->fresh(['category', 'author', 'teamMembers.user']);

        if ($resubmitted) {
            MarketplaceNotifier::resourcePending($resource);
        }

        return $resource;
    }

    public function attachGateway(array $input): MarketplaceResource
    {
        $validated = Validator::make($input, [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'resource_id' => ['required', 'integer', 'exists:marketplace_resources,id'],
            'gateway_config_id' => ['required', 'integer', 'exists:marketplace_creator_gateway_configs,id'],
        ])->validate();

        $user = $this->user((int) $validated['user_id']);
        $resource = MarketplaceResource::findOrFail($validated['resource_id']);
        $this->assertCanManageResource($user, $resource, TeamRole::Manager);
        $this->assertGatewayBelongsToOwner($resource, (int) $validated['gateway_config_id']);

        $resource->update(['gateway_config_id' => $validated['gateway_config_id']]);

        return $resource->fresh();
    }

    public function approveAsAdmin(array $input): MarketplaceResource
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'resource_id' => ['required', 'integer', 'exists:marketplace_resources,id'],
        ])->validate();

        $admin = $this->staffUser((int) $validated['admin_user_id']);
        $resource = MarketplaceResource::findOrFail($validated['resource_id']);

        $resource->update([
            'status' => ResourceStatus::Approved,
            'rejection_reason' => null,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'published_at' => $resource->published_at ?? now(),
        ]);

        $resource->versions()
            ->where('status', VersionStatus::Pending)
            ->update(['status' => VersionStatus::Approved->value]);

        $resource = $resource->fresh(['category', 'author', 'teamMembers.user']);
        MarketplaceNotifier::resourceApproved($resource);

        return $resource;
    }

    public function rejectAsAdmin(array $input): MarketplaceResource
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'resource_id' => ['required', 'integer', 'exists:marketplace_resources,id'],
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ])->validate();

        $this->staffUser((int) $validated['admin_user_id']);
        $resource = MarketplaceResource::findOrFail($validated['resource_id']);

        $resource->update([
            'status' => ResourceStatus::Rejected,
            'rejection_reason' => $validated['rejection_reason'],
            'approved_at' => null,
            'approved_by' => null,
            'published_at' => null,
        ]);

        $resource = $resource->fresh(['category', 'author', 'teamMembers.user']);
        MarketplaceNotifier::resourceRejected($resource);

        return $resource;
    }

    public function suspendAsAdmin(array $input): MarketplaceResource
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'resource_id' => ['required', 'integer', 'exists:marketplace_resources,id'],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $this->staffUser((int) $validated['admin_user_id']);
        $resource = MarketplaceResource::findOrFail($validated['resource_id']);

        $resource->update([
            'status' => ResourceStatus::Suspended,
            'rejection_reason' => $validated['rejection_reason'] ?? $resource->rejection_reason,
        ]);

        $resource = $resource->fresh(['category', 'author', 'teamMembers.user']);
        MarketplaceNotifier::resourceSuspended($resource);

        return $resource;
    }

    public function featureAsAdmin(array $input): MarketplaceResource
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'resource_id' => ['required', 'integer', 'exists:marketplace_resources,id'],
            'is_featured' => ['required', 'boolean'],
            'featured_until' => ['nullable', 'date'],
        ])->validate();

        $this->staffUser((int) $validated['admin_user_id']);
        $resource = MarketplaceResource::findOrFail($validated['resource_id']);
        $wasFeatured = $resource->isFeaturedNow();

        $resource->update([
            'is_featured' => $validated['is_featured'],
            'featured_until' => $validated['is_featured'] ? ($validated['featured_until'] ?? null) : null,
        ]);

        $resource = $resource->fresh(['category', 'author', 'teamMembers.user']);

        if (! $wasFeatured && $resource->isFeaturedNow()) {
            MarketplaceNotifier::resourceFeatured($resource);
        }

        return $resource;
    }

    public function addTeamMember(array $input): MarketplaceResourceTeamMember
    {
        $validated = Validator::make($input, [
            'actor_user_id' => ['required', 'integer', 'exists:users,id'],
            'resource_id' => ['required', 'integer', 'exists:marketplace_resources,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', Rule::enum(TeamRole::class)],
        ])->validate();

        $actor = $this->user((int) $validated['actor_user_id']);
        $resource = MarketplaceResource::findOrFail($validated['resource_id']);

        if (! $resource->staffCanManage($actor)) {
            $this->assertCanManageResource($actor, $resource, TeamRole::Owner);
        }

        if ((int) $validated['user_id'] === (int) $resource->user_id) {
            throw ValidationException::withMessages([
                'user_id' => 'The resource owner is already on the team.',
            ]);
        }

        $role = TeamRole::from($validated['role']);

        if ($role === TeamRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'Ownership cannot be assigned this way.',
            ]);
        }

        $member = MarketplaceResourceTeamMember::query()->updateOrCreate(
            [
                'resource_id' => $resource->id,
                'user_id' => $validated['user_id'],
            ],
            [
                'role' => $role,
                'invited_by' => $actor->id,
            ]
        );

        MarketplaceNotifier::teamMemberInvited($resource->fresh(['category']), $member->fresh('user'), $actor);

        return $member;
    }

    public function removeTeamMember(array $input): bool
    {
        $validated = Validator::make($input, [
            'actor_user_id' => ['required', 'integer', 'exists:users,id'],
            'resource_id' => ['required', 'integer', 'exists:marketplace_resources,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ])->validate();

        $actor = $this->user((int) $validated['actor_user_id']);
        $resource = MarketplaceResource::findOrFail($validated['resource_id']);

        if (! $resource->staffCanManage($actor)) {
            $this->assertCanManageResource($actor, $resource, TeamRole::Owner);
        }

        if ((int) $validated['user_id'] === (int) $resource->user_id) {
            throw ValidationException::withMessages([
                'user_id' => 'The resource owner cannot be removed.',
            ]);
        }

        return (bool) MarketplaceResourceTeamMember::query()
            ->where('resource_id', $resource->id)
            ->where('user_id', $validated['user_id'])
            ->delete();
    }

    public function recordView(MarketplaceResource $resource, ?User $user = null): MarketplaceResource
    {
        $hash = MarketplaceResource::visitorHash($user);

        $alreadyCounted = $resource->views()
            ->where('visitor_hash', $hash)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($alreadyCounted) {
            return $resource;
        }

        $resource->views()->create([
            'user_id' => $user?->id,
            'visitor_hash' => $hash,
        ]);

        $resource->increment('views_count');

        return $resource->fresh();
    }

    public function deleteAsAdmin(array $input): bool
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'resource_id' => ['required', 'integer', 'exists:marketplace_resources,id'],
        ])->validate();

        $this->staffUser((int) $validated['admin_user_id'], 'admin.marketplace.delete');

        $resource = MarketplaceResource::findOrFail($validated['resource_id']);
        $this->deleteIcon($resource);

        foreach ($resource->versions as $version) {
            MarketplaceResourceVersionActions::deleteStoredFile($version);
        }

        return (bool) $resource->delete();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'category_id' => [$required, 'integer', 'exists:marketplace_categories,id'],
            'gateway_config_id' => ['nullable', 'integer', 'exists:marketplace_creator_gateway_configs,id'],
            'name' => [$required, 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140'],
            'short_description' => [$required, 'string', 'max:240'],
            'description' => [$required, 'string', 'max:100000'],
            'icon' => ['nullable', 'file', 'image', 'max:'.self::MAX_ICON_KILOBYTES],
            'website_url' => ['nullable', 'url', 'max:255'],
            'docs_url' => ['nullable', 'url', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:255'],
            'support_url' => ['nullable', 'url', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:999999'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'license_type' => ['sometimes', 'string', Rule::in(MarketplaceResource::licenseTypes())],
            'tags' => ['nullable'],
            'available_on_integrated_marketplace' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{disk: string, path: string}|null
     */
    protected function storeIcon(?UploadedFile $file): ?array
    {
        if (! $file) {
            return null;
        }

        $path = $file->store('marketplace/icons', 'public');

        return [
            'disk' => 'public',
            'path' => $path,
        ];
    }

    protected function deleteIcon(MarketplaceResource $resource): void
    {
        if ($resource->icon_path && $resource->icon_disk) {
            Storage::disk($resource->icon_disk)->delete($resource->icon_path);
        }
    }

    protected function assertGatewayBelongsToOwner(MarketplaceResource $resource, int $gatewayConfigId): void
    {
        $config = MarketplaceCreatorGatewayConfig::query()
            ->where('id', $gatewayConfigId)
            ->where('user_id', $resource->user_id)
            ->where('is_enabled', true)
            ->first();

        if (! $config) {
            throw ValidationException::withMessages([
                'gateway_config_id' => 'Select a payment method owned by the resource creator.',
            ]);
        }
    }
}
