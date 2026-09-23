<?php

use App\Models\User;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public int $resourceId;

    public string $rejection_reason = '';

    public bool $is_featured = false;

    public bool $is_official = false;

    public string $member_username = '';

    public function mount(): void
    {
        $this->is_featured = $this->resource->is_featured;
        $this->is_official = $this->resource->is_official;
        $this->rejection_reason = (string) $this->resource->rejection_reason;
    }

    #[Computed]
    public function resource(): MarketplaceResource
    {
        return MarketplaceResource::query()
            ->with(['category', 'author', 'versions', 'teamMembers.user', 'gatewayConfigs'])
            ->findOrFail($this->resourceId);
    }

    public function approve(): void
    {
        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => auth()->id(),
            'resource_id' => $this->resourceId,
        ]);

        unset($this->resource);
    }

    public function reject(): void
    {
        MarketplaceResource::actions()->rejectAsAdmin([
            'admin_user_id' => auth()->id(),
            'resource_id' => $this->resourceId,
            'rejection_reason' => $this->rejection_reason,
        ]);

        unset($this->resource);
    }

    public function suspend(): void
    {
        MarketplaceResource::actions()->suspendAsAdmin([
            'admin_user_id' => auth()->id(),
            'resource_id' => $this->resourceId,
            'rejection_reason' => $this->rejection_reason ?: null,
        ]);

        unset($this->resource);
    }

    public function toggleFeatured(): void
    {
        MarketplaceResource::actions()->featureAsAdmin([
            'admin_user_id' => auth()->id(),
            'resource_id' => $this->resourceId,
            'is_featured' => $this->is_featured,
        ]);

        unset($this->resource);
    }

    public function toggleOfficial(): void
    {
        MarketplaceResource::actions()->setOfficialAsAdmin([
            'admin_user_id' => auth()->id(),
            'resource_id' => $this->resourceId,
            'is_official' => $this->is_official,
        ]);

        unset($this->resource);
    }

    public function deleteResource(): mixed
    {
        MarketplaceResource::actions()->deleteAsAdmin([
            'admin_user_id' => auth()->id(),
            'resource_id' => $this->resourceId,
        ]);

        session()->flash('success', 'Resource deleted.');

        return $this->redirect(route('admin.marketplace.resources.index'), navigate: true);
    }

    public function addMember(): void
    {
        $user = User::query()->where('username', $this->member_username)->orWhere('email', $this->member_username)->first();

        if (! $user) {
            $this->addError('member_username', 'No user matches that username or email.');

            return;
        }

        MarketplaceResource::actions()->addTeamMember([
            'actor_user_id' => auth()->id(),
            'resource_id' => $this->resourceId,
            'user_id' => $user->id,
        ]);

        $this->member_username = '';
        unset($this->resource);
    }

    public function removeMember(int $userId): void
    {
        MarketplaceResource::actions()->removeTeamMember([
            'actor_user_id' => auth()->id(),
            'resource_id' => $this->resourceId,
            'user_id' => $userId,
        ]);

        unset($this->resource);
    }
}

?>

@php $resource = $this->resource; @endphp

<div>
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">{{ $resource->name }}</h3>
                    <div class="card-actions">
                        <span class="badge bg-secondary-lt">{{ $resource->status->label() }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-secondary">{{ $resource->short_description }}</p>
                    <div class="markdown">{!! $resource->renderedDescription() !!}</div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Versions</h3>
                </div>
                <div class="list-group list-group-flush">
                    @foreach($resource->versions as $version)
                        <div class="list-group-item" wire:key="admin-ver-{{ $version->id }}">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-medium">{{ $version->name }} v{{ $version->version }}</div>
                                    <div class="text-secondary small">
                                        {{ $version->created_at?->diffForHumans() }}
                                        · WemX {{ $version->wemx_version }}
                                        · {{ $version->humanSize() }}
                                    </div>
                                    @if($version->extract_path)
                                        <div class="text-secondary small">Extract <code>{{ $version->extract_path }}</code></div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Team</h3>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-3">
                        @foreach($resource->teamMembers as $member)
                            <li class="d-flex justify-content-between mb-2" wire:key="admin-member-{{ $member->id }}">
                                <span>{{ $member->user?->username }} <span class="text-secondary">{{ $member->role->label() }}</span></span>
                                @if($member->role !== TeamRole::Owner)
                                    <button type="button" class="btn btn-link btn-sm text-danger" wire:click="removeMember({{ $member->user_id }})">Remove</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <form wire:submit="addMember" class="row g-2">
                        <div class="col-md-9">
                            <input class="form-control" wire:model="member_username" placeholder="Username or email">
                            @error('member_username') <x-admin::form.error :message="$message"/> @enderror
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" type="submit">Add collaborator</button>
                        </div>
                    </form>
                    <p class="text-secondary small mt-2 mb-0">Collaborators get full access to this resource.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Moderation</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Review note / rejection reason</label>
                        <textarea class="form-control" rows="3" wire:model="rejection_reason"></textarea>
                    </div>
                    <div class="btn-list">
                        <button type="button" class="btn btn-success" wire:click="approve">Approve</button>
                        <button type="button" class="btn btn-outline-danger" wire:click="reject">Reject</button>
                        <button type="button" class="btn btn-outline-warning" wire:click="suspend">Suspend</button>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" wire:model.live="is_featured" wire:change="toggleFeatured" id="featured">
                        <label class="form-check-label" for="featured">Featured on the marketplace</label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" wire:model.live="is_official" wire:change="toggleOfficial" id="official">
                        <label class="form-check-label" for="official">Official resource</label>
                    </div>
                    <hr class="my-3">
                    @if($resource->canBeDeleted())
                        <button
                            type="button"
                            class="btn btn-outline-danger w-100"
                            wire:click="deleteResource"
                            wire:confirm="Delete this resource permanently?"
                        >Delete resource</button>
                    @else
                        <div class="alert alert-warning mb-0">Paid resources with purchases cannot be deleted.</div>
                    @endif
                    @error('resource_id') <x-admin::form.error :message="$message"/> @enderror
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="datagrid">
                        <div class="datagrid-item">
                            <div class="datagrid-title">Author</div>
                            <div class="datagrid-content">{{ $resource->author?->username }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Category</div>
                            <div class="datagrid-content">{{ $resource->category?->name }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Price</div>
                            <div class="datagrid-content">{{ $resource->formattedPrice() }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Listing</div>
                            <div class="datagrid-content">{{ $resource->is_disabled ? 'Disabled' : 'Listed' }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Payment methods</div>
                            <div class="datagrid-content">
                                @forelse($resource->gatewayConfigs as $gateway)
                                    <div>{{ $gateway->name }} ({{ $gateway->driverName() }})</div>
                                @empty
                                    —
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
