@php
    /** @var array<string, mixed> $resource */
    $latest = $resource['latest_version'] ?? ($resource['versions'][0]['version'] ?? null);
@endphp

<div class="col-sm-6 col-lg-4" wire:key="resource-{{ $resource['slug'] }}">
    <a href="{{ route('admin.marketplace.show', $resource['slug']) }}" wire:navigate class="card card-link h-100">
        <div class="card-body">
            <div class="d-flex gap-3">
                @include('admin::integrated-marketplace.partials.icon', ['resource' => $resource, 'size' => 48])
                <div class="min-w-0">
                    <div class="d-flex flex-wrap gap-1 mb-1">
                        @if(! empty($resource['category']['name']))
                            <span class="badge bg-blue-lt">{{ $resource['category']['name'] }}</span>
                        @endif
                        <span class="badge {{ ($resource['price'] ?? '') === 'Free' ? 'bg-green-lt' : 'bg-yellow-lt' }}">{{ $resource['price'] }}</span>
                        @if(! empty($resource['featured']))
                            <span class="badge bg-purple-lt">Featured</span>
                        @endif
                        @if(! empty($resource['official']))
                            <span class="badge bg-azure-lt">Official</span>
                        @endif
                    </div>
                    <h3 class="card-title mb-1 text-truncate">{{ $resource['name'] }}</h3>
                    <div class="text-secondary" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ $resource['short_description'] }}</div>
                    @if(($resource['reviews_count'] ?? 0) > 0)
                        <div class="mt-2">
                            @include('admin::integrated-marketplace.partials.stars', ['rating' => $resource['reviews_avg'], 'count' => $resource['reviews_count']])
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="d-flex flex-wrap justify-content-between gap-2 text-secondary small">
                <span class="text-truncate">{{ $resource['user']['username'] ?? 'Unknown' }}</span>
                <span>
                    {{ number_format((int) ($resource['downloads'] ?? 0)) }} downloads
                    · {{ number_format((int) ($resource['purchases'] ?? 0)) }} purchases
                    @if($latest)
                        · v{{ $latest }}
                    @endif
                </span>
            </div>
        </div>
    </a>
</div>
