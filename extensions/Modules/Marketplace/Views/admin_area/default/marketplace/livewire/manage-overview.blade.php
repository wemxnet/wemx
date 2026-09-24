<?php

use Extensions\Modules\Marketplace\Enums\ResourceStatus;
use Extensions\Modules\Marketplace\Enums\SaleStatus;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Models\MarketplaceSale;
use Livewire\Volt\Component;

new class extends Component {}

?>

@php
    $pending = MarketplaceResource::query()->where('status', ResourceStatus::Pending)->count();
    $approved = MarketplaceResource::query()->approved()->count();
    $featured = MarketplaceResource::query()->featured()->count();
    $sales = MarketplaceSale::query()->where('status', SaleStatus::Completed)->count();
    $licenses = MarketplaceLicense::query()->count();
    $queue = MarketplaceResource::query()
        ->with(['category', 'author'])
        ->where('status', ResourceStatus::Pending)
        ->latest()
        ->limit(8)
        ->get();
    $popular = MarketplaceResource::query()
        ->with('category')
        ->approved()
        ->popular()
        ->limit(8)
        ->get();
@endphp

<div>
    <div class="row row-deck row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Pending review</div>
                    <div class="h1 mb-0">{{ $pending }}</div>
                    <div class="text-secondary">Waiting for approval</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Approved</div>
                    <div class="h1 mb-0">{{ $approved }}</div>
                    <div class="text-secondary">{{ $featured }} featured</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Completed sales</div>
                    <div class="h1 mb-0">{{ $sales }}</div>
                    <div class="text-secondary">Creator payouts via their own gateways</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Purchases</div>
                    <div class="h1 mb-0">{{ $licenses }}</div>
                    <div class="text-secondary">Issued to buyers and free downloads</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Approval queue</h3>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($queue as $resource)
                        <a href="{{ route('admin.marketplace-manager.resources.show', $resource) }}" wire:navigate wire:key="queue-{{ $resource->id }}" class="list-group-item list-group-item-action">
                            <div class="fw-medium">{{ $resource->name }}</div>
                            <div class="text-secondary small">{{ $resource->category?->name }} · {{ $resource->author?->username }}</div>
                        </a>
                    @empty
                        <div class="list-group-item text-secondary">Nothing waiting for review.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Most popular</h3>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($popular as $resource)
                        <a href="{{ route('admin.marketplace-manager.resources.show', $resource) }}" wire:navigate wire:key="pop-{{ $resource->id }}" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between">
                                <span class="fw-medium">{{ $resource->name }}</span>
                                <span class="text-secondary small">{{ $resource->views_count + $resource->downloads_count }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="list-group-item text-secondary">No approved resources yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
