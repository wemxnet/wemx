<?php

use Extensions\Modules\Marketplace\Models\MarketplaceCategory;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }
}

?>

@php
    $query = MarketplaceResource::query()->with(['category', 'author'])->latest();

    $query = match ($this->status) {
        'pending' => $query->where('status', 'pending'),
        'approved' => $query->approved(),
        'rejected' => $query->where('status', 'rejected'),
        'suspended' => $query->where('status', 'suspended'),
        'featured' => $query->featured(),
        'official' => $query->official(),
        'disabled' => $query->where('is_disabled', true),
        default => $query,
    };

    if ($this->search !== '') {
        $query->search($this->search);
    }

    $resources = $query->paginate(20);
    $categories = MarketplaceCategory::query()->ordered()->get();
@endphp

<div>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Resources</h3>
            <div class="card-actions">
                <div class="d-flex flex-wrap gap-2">
                    <select class="form-select form-select-sm" wire:model.live="status">
                        <option value="all">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="suspended">Suspended</option>
                        <option value="featured">Featured</option>
                        <option value="official">Official</option>
                        <option value="disabled">Disabled</option>
                    </select>
                    <input type="search" class="form-control form-control-sm" style="min-width: 14rem;" wire:model.live.debounce.300ms="search" placeholder="Search resources">
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Resource</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Popularity</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resources as $resource)
                        <tr wire:key="res-{{ $resource->id }}">
                            <td>
                                <a href="{{ route('admin.marketplace.resources.show', $resource) }}" wire:navigate>{{ $resource->name }}</a>
                                <div class="text-secondary">{{ $resource->category?->name }} · {{ $resource->formattedPrice() }}</div>
                            </td>
                            <td>{{ $resource->author?->username }}</td>
                            <td>
                                <span class="badge bg-secondary-lt">{{ $resource->status->label() }}</span>
                                @if($resource->isFeaturedNow())
                                    <span class="badge bg-blue-lt">Featured</span>
                                @endif
                                @if($resource->is_official)
                                    <span class="badge bg-cyan-lt">Official</span>
                                @endif
                                @if($resource->is_disabled)
                                    <span class="badge bg-orange-lt">Disabled</span>
                                @endif
                            </td>
                            <td>{{ $resource->views_count }} views · {{ $resource->downloads_count }} downloads</td>
                            <td class="text-end">
                                <a href="{{ route('admin.marketplace.resources.show', $resource) }}" wire:navigate>Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-secondary">No resources match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($resources->hasPages())
            <div class="card-footer">{{ $resources->links() }}</div>
        @endif
    </div>
</div>
