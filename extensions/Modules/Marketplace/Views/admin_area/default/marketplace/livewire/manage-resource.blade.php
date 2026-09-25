<?php

use App\Models\User;
use Extensions\Modules\Marketplace\Enums\LicenseStatus;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public int $resourceId;

    public string $rejection_reason = '';

    public bool $is_featured = false;

    public bool $is_official = false;

    public string $member_username = '';

    #[Url]
    public string $access_q = '';

    public string $access_username = '';

    public string $access_payment_method = '';

    public string $access_transaction_id = '';

    public bool $access_notify = true;

    public function mount(): void
    {
        $this->is_featured = $this->resource->is_featured;
        $this->is_official = $this->resource->is_official;
        $this->rejection_reason = (string) $this->resource->rejection_reason;
    }

    public function updatingAccessQ(): void
    {
        $this->resetPage();
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

        return $this->redirect(route('admin.marketplace-manager.resources.index'), navigate: true);
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

    public function grantAccess(): void
    {
        MarketplaceLicense::actions()->grantAsManager([
            'actor_user_id' => auth()->id(),
            'resource_id' => $this->resourceId,
            'username' => $this->access_username,
            'payment_method' => $this->access_payment_method ?: null,
            'transaction_id' => $this->access_transaction_id ?: null,
            'notify' => $this->access_notify,
        ]);

        $this->reset(['access_username', 'access_payment_method', 'access_transaction_id']);
        $this->access_notify = true;
        $this->resetPage();
        session()->flash('success', 'Purchase access granted.');
    }

    public function revokeAccess(int $licenseId): void
    {
        MarketplaceLicense::actions()->revoke([
            'actor_user_id' => auth()->id(),
            'license_id' => $licenseId,
        ]);

        session()->flash('success', 'Access revoked.');
    }
}

?>

@php
    $resource = $this->resource;
    $licenses = MarketplaceLicense::query()
        ->with(['user', 'sale', 'granter'])
        ->where('resource_id', $resourceId)
        ->search($this->access_q)
        ->orderByDesc('purchased_at')
        ->orderByDesc('created_at')
        ->paginate(20);
@endphp

<div>
    @if(session('success'))
        <div class="alert alert-success mb-3">{{ session('success') }}</div>
    @endif

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
                                @if($version->downloadableFromExtensionMarketplace($resource))
                                    <a href="{{ route('marketplace.versions.download', $version) }}" class="btn btn-sm btn-outline-primary">Download</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Access</h3>
                </div>
                <div class="card-body border-bottom">
                    <h4 class="mb-3">Grant purchase access</h4>
                    <form wire:submit="grantAccess" class="row g-2">
                        <div class="col-md-6">
                            <input class="form-control" wire:model="access_username" placeholder="Username or email">
                            @error('username') <x-admin::form.error :message="$message"/> @enderror
                        </div>
                        <div class="col-md-3">
                            <input class="form-control" wire:model="access_payment_method" placeholder="Payment method (optional)">
                        </div>
                        <div class="col-md-3">
                            <input class="form-control" wire:model="access_transaction_id" placeholder="Transaction ID (optional)">
                        </div>
                        <div class="col-12">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" wire:model="access_notify">
                                <span class="form-check-label">Email the customer about their access</span>
                            </label>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">Add user</button>
                        </div>
                    </form>
                    <p class="text-secondary small mt-2 mb-0">Grants download and purchase access to this resource.</p>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-3">
                        <div>
                            <h4 class="mb-1">Users with access</h4>
                            <p class="text-secondary small mb-0">Customers who purchased, downloaded for free, or were granted access.</p>
                        </div>
                        <div class="w-100 w-md-auto" style="min-width: 16rem;">
                            <input type="search" class="form-control" wire:model.live.debounce.300ms="access_q" placeholder="Search user, key, payment…">
                        </div>
                    </div>

                    @if($licenses->isEmpty())
                        <p class="text-secondary mb-0">
                            {{ $this->access_q !== '' ? 'No matching users.' : 'No users have access yet.' }}
                        </p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-vcenter">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Purchased</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($licenses as $license)
                                        <tr wire:key="admin-access-{{ $license->id }}">
                                            <td>
                                                <div>{{ $license->user?->username }}</div>
                                                <div class="text-secondary small">{{ $license->user?->email }}</div>
                                                <div class="text-secondary small"><code>{{ $license->license_key }}</code></div>
                                            </td>
                                            <td>{{ $license->purchasedAt()?->format('Y-m-d') ?? '—' }}</td>
                                            <td>{{ $license->paymentMethodLabel() }}</td>
                                            <td>{{ $license->status->label() }}</td>
                                            <td class="text-end">
                                                @if($license->status === LicenseStatus::Active)
                                                    <button type="button" class="btn btn-link btn-sm text-danger" wire:click="revokeAccess({{ $license->id }})" wire:confirm="Revoke this user's access?">Revoke</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($licenses->hasPages())
                            <div class="mt-3">{{ $licenses->links() }}</div>
                        @endif
                    @endif
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
                    <p class="text-secondary small mt-2 mb-0">Collaborators get full access to manage this resource.</p>
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
