<?php

use App\Services\IntegratedMarketplace;
use App\Services\IntegratedMarketplaceInstaller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component
{
    public string $slug;

    #[Url(as: 'tab')]
    public string $tab = 'resource';

    public ?int $installVersionId = null;

    public bool $installAcknowledged = false;

    public function mount(): void
    {
        if (! in_array($this->tab, ['resource', 'versions', 'reviews'], true)) {
            $this->tab = 'resource';
        }

        app(IntegratedMarketplace::class)->recordView($this->slug);
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['resource', 'versions', 'reviews'], true) ? $tab : 'resource';
    }

    public function openInstall(int $versionId): void
    {
        $this->installVersionId = $versionId;
        $this->installAcknowledged = false;
    }

    public function closeInstall(): void
    {
        $this->installVersionId = null;
        $this->installAcknowledged = false;
    }

    public function install(): void
    {
        if (! $this->installAcknowledged || $this->installVersionId === null) {
            return;
        }

        $versionId = $this->installVersionId;
        $this->closeInstall();

        try {
            session()->flash('success', app(IntegratedMarketplaceInstaller::class)->install($this->slug, $versionId));
        } catch (\RuntimeException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    /**
     * @return array{resource: array<string, mixed>|null, error: string|null}
     */
    #[Computed]
    public function payload(): array
    {
        return app(IntegratedMarketplace::class)->resource($this->slug);
    }

    public function renderMarkdown(?string $markdown): string
    {
        return Str::markdown($markdown ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}

?>

@php
    $payload = $this->payload;
    $resource = $payload['resource'];
    $tab = $this->tab;
    $versions = collect($resource['versions'] ?? []);
    $reviews = collect($resource['reviews'] ?? []);
    $latest = $versions->first();
    $installVersion = $versions->firstWhere('id', $this->installVersionId);
    $installCompatible = ! is_array($installVersion) || app(IntegratedMarketplaceInstaller::class)->supportsCurrentVersion((string) ($installVersion['wemx_version'] ?? ''));
@endphp

<div>
    <div class="mb-3">
        <a href="{{ route('admin.marketplace.index') }}" wire:navigate class="text-secondary">Marketplace</a>
        <span class="text-secondary px-1">/</span>
        <span class="text-secondary">{{ $resource['category']['name'] ?? 'Resource' }}</span>
        <span class="text-secondary px-1">/</span>
        <span>{{ $resource['name'] ?? $slug }}</span>
    </div>

    @if($payload['error'])
        <div class="alert alert-warning" role="alert">{{ $payload['error'] }}</div>
    @endif

    @if(session('success'))
        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    @if(! $resource)
        <div class="empty">
            <p class="empty-title">Resource unavailable</p>
            <p class="empty-subtitle text-secondary">This listing is not on the marketplace, or the catalog could not be loaded.</p>
            <div class="empty-action">
                <a href="{{ route('admin.marketplace.index') }}" wire:navigate class="btn btn-primary">Back to marketplace</a>
            </div>
        </div>
    @else
        <div class="row row-cards">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            @include('admin::integrated-marketplace.partials.icon', ['resource' => $resource, 'size' => 64])
                            <div class="min-w-0">
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    @if(! empty($resource['category']['name']))
                                        <span class="badge bg-blue-lt">{{ $resource['category']['name'] }}</span>
                                    @endif
                                    <span class="badge {{ ($resource['price'] ?? '') === 'Free' ? 'bg-green-lt' : 'bg-yellow-lt' }}">{{ $resource['price'] }}</span>
                                    @if(! empty($resource['featured']))
                                        <span class="badge bg-purple-lt">Featured</span>
                                    @endif
                                    @if($latest)
                                        <span class="badge bg-secondary-lt">v{{ $latest['version'] }}</span>
                                    @endif
                                </div>
                                <h2 class="mb-1 d-flex align-items-center gap-2">
                                    <span>{{ $resource['name'] }}</span>
                                    @if(! empty($resource['official']))
                                        <i class="ti ti-discount-check text-azure" title="Official. This resource is from a trusted developer."></i>
                                    @endif
                                </h2>
                                <div class="text-secondary">{{ $resource['short_description'] }}</div>
                                @if(($resource['reviews_count'] ?? 0) > 0)
                                    <div class="mt-2">
                                        @include('admin::integrated-marketplace.partials.stars', ['rating' => $resource['reviews_avg'], 'count' => $resource['reviews_count']])
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <button type="button" wire:click="setTab('resource')" class="nav-link {{ $tab === 'resource' ? 'active' : '' }}">Resource</button>
                            </li>
                            <li class="nav-item">
                                <button type="button" wire:click="setTab('versions')" class="nav-link {{ $tab === 'versions' ? 'active' : '' }}">Versions @if($versions->isNotEmpty())({{ $versions->count() }})@endif</button>
                            </li>
                            <li class="nav-item">
                                <button type="button" wire:click="setTab('reviews')" class="nav-link {{ $tab === 'reviews' ? 'active' : '' }}">Reviews @if(($resource['reviews_count'] ?? 0) > 0)({{ $resource['reviews_count'] }})@endif</button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        @if($tab === 'resource')
                            <h3 class="mb-3">Description</h3>
                            <div class="markdown">{!! $this->renderMarkdown($resource['description'] ?? '') !!}</div>
                        @elseif($tab === 'versions')
                            <ul class="timeline mb-0">
                                @forelse($versions as $index => $version)
                                    <li wire:key="version-{{ $version['id'] }}" class="timeline-event">
                                        <div class="timeline-event-icon {{ $index === 0 ? 'bg-primary-lt text-primary' : 'bg-secondary-lt text-secondary' }}">
                                            <x-admin::icon icon="versions" outline class="icon icon-1"/>
                                        </div>
                                        <div class="card timeline-event-card border bg-body">
                                            <div class="card-body">
                                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                                        <strong>{{ $version['name'] }}</strong>
                                                        <span class="badge bg-secondary-lt font-monospace">v{{ $version['version'] }}</span>
                                                        @if($index === 0)
                                                            <span class="badge bg-blue-lt">Latest</span>
                                                        @endif
                                                        @if(! empty($version['integrated_marketplace']))
                                                            <span class="badge bg-green-lt">One-click install</span>
                                                        @endif
                                                    </div>
                                                    @if(! empty($version['integrated_marketplace']))
                                                        <button
                                                            type="button"
                                                            class="btn btn-primary btn-sm"
                                                            wire:click="openInstall({{ (int) $version['id'] }})"
                                                        >
                                                            Install {{ $version['version'] }}
                                                        </button>
                                                    @endif
                                                </div>
                                                @if(! empty($version['integrated_marketplace']) && ! app(IntegratedMarketplaceInstaller::class)->supportsCurrentVersion((string) ($version['wemx_version'] ?? '')))
                                                    <div class="alert alert-warning mt-3 mb-0" role="alert">This version requires WemX {{ $version['wemx_version'] }}. This site is running {{ config('app.version') }}.</div>
                                                @endif
                                                <div class="text-secondary small mt-1">
                                                    {{ $version['created_at'] ? Carbon::parse($version['created_at'])->timezone(config('app.timezone'))->format('M j, Y g:i A') : '' }}
                                                    · WemX {{ $version['wemx_version'] }}
                                                    · {{ $version['size_label'] ?? '' }}
                                                </div>
                                                <div class="markdown mt-3">{!! $this->renderMarkdown($version['changelog'] ?? '') !!}</div>
                                            </div>
                                        </div>
                                    </li>
                                @empty
                                    <li class="text-secondary">No versions published yet.</li>
                                @endforelse
                            </ul>
                        @else
                            @forelse($reviews as $review)
                                <div wire:key="review-{{ $review['id'] }}" class="border rounded p-3 {{ $loop->last ? '' : 'mb-3' }}">
                                    <div class="d-flex justify-content-between gap-3">
                                        <div class="d-flex align-items-center gap-2">
                                            @if(! empty($review['user']['avatar']))
                                                <span class="avatar avatar-sm" style="background-image: url({{ $review['user']['avatar'] }})"></span>
                                            @endif
                                            <div>
                                                <div class="fw-medium">{{ $review['user']['username'] ?? 'User' }}</div>
                                                <div class="text-secondary small">{{ $review['created_at'] ? Carbon::parse($review['created_at'])->diffForHumans() : '' }}</div>
                                            </div>
                                        </div>
                                        @include('admin::integrated-marketplace.partials.stars', ['rating' => $review['rating']])
                                    </div>
                                    @if(! empty($review['title']))
                                        <h4 class="mt-3 mb-1">{{ $review['title'] }}</h4>
                                    @endif
                                    <p class="mb-0 mt-2" style="white-space: pre-line;">{{ $review['body'] }}</p>
                                </div>
                            @empty
                                <p class="text-secondary mb-0">No reviews yet.</p>
                            @endforelse
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            @if(! empty($resource['user']['avatar']))
                                <span class="avatar" style="background-image: url({{ $resource['user']['avatar'] }})"></span>
                            @endif
                            <div>
                                <div class="text-secondary small">Author</div>
                                <div class="fw-medium">{{ $resource['user']['username'] ?? 'Unknown' }}</div>
                            </div>
                        </div>
                        <div class="row text-center mt-3">
                            <div class="col">
                                <div class="text-secondary small">Views</div>
                                <div class="fw-bold">{{ number_format((int) ($resource['views'] ?? 0)) }}</div>
                            </div>
                            <div class="col">
                                <div class="text-secondary small">Downloads</div>
                                <div class="fw-bold">{{ number_format((int) ($resource['downloads'] ?? 0)) }}</div>
                            </div>
                            <div class="col">
                                <div class="text-secondary small">Purchases</div>
                                <div class="fw-bold">{{ number_format((int) ($resource['purchases'] ?? 0)) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h3 class="card-title">Links</h3>
                        <div class="d-flex flex-column gap-2">
                            @if(! empty($resource['website']))
                                <a href="{{ $resource['website'] }}" target="_blank" rel="noopener">Website</a>
                            @endif
                            @if(! empty($resource['docs']))
                                <a href="{{ $resource['docs'] }}" target="_blank" rel="noopener">Documentation</a>
                            @endif
                            @if(! empty($resource['source']))
                                <a href="{{ $resource['source'] }}" target="_blank" rel="noopener">Source</a>
                            @endif
                            @if(! empty($resource['support']))
                                <a href="{{ $resource['support'] }}" target="_blank" rel="noopener">Support</a>
                            @endif
                            @if(empty($resource['website']) && empty($resource['docs']) && empty($resource['source']) && empty($resource['support']))
                                <span class="text-secondary">No links provided.</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="h2 mb-1">{{ $resource['price'] }}</div>
                        @if(($resource['reviews_count'] ?? 0) > 0)
                            <div class="mb-3">
                                @include('admin::integrated-marketplace.partials.stars', ['rating' => $resource['reviews_avg'], 'count' => $resource['reviews_count']])
                            </div>
                        @endif
                        @if($latest && ! empty($latest['integrated_marketplace']))
                            <button
                                type="button"
                                class="btn btn-primary w-100 mb-2"
                                wire:click="openInstall({{ (int) $latest['id'] }})"
                            >
                                Install {{ $latest['version'] }}
                            </button>
                        @endif
                        @if(! empty($resource['view_url']))
                            <a href="{{ $resource['view_url'] }}" class="btn btn-outline-primary w-100">View on marketplace</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if(is_array($installVersion))
            <div class="modal modal-blur show d-block" tabindex="-1" role="dialog" style="background: rgba(0, 0, 0, .4);">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Install {{ $installVersion['version'] }}</h5>
                            <button type="button" class="btn-close" wire:click="closeInstall" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @if(! $installCompatible)
                                <div class="alert alert-warning" role="alert">This version requires WemX {{ $installVersion['wemx_version'] }}. This site is running {{ config('app.version') }}, so it may not work.</div>
                            @endif
                            <p>You are installing a resource from a third-party developer. Review the listing before adding it to this site.</p>
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" wire:model.live="installAcknowledged">
                                <span class="form-check-label">I understand this resource is provided by a third-party developer.</span>
                            </label>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-link link-secondary" wire:click="closeInstall">Cancel</button>
                            <button type="button" class="btn btn-primary" wire:click="install" wire:loading.attr="disabled" @disabled(! $installAcknowledged)>
                                <span wire:loading.remove wire:target="install">Install {{ $installVersion['version'] }}</span>
                                <span wire:loading wire:target="install">Installing…</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
