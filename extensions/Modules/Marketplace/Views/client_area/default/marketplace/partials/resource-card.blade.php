@php
    /** @var \Extensions\Modules\Marketplace\Models\MarketplaceResource $resource */
    $latest = $resource->latestApprovedVersion() ?? $resource->latestVersion();
@endphp

<div
    wire:key="resource-{{ $resource->id }}"
    class="group flex h-full flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-600"
>
    <a href="{{ $resource->clientUrl() }}" wire:navigate class="flex min-h-0 flex-1 flex-col">
        <div class="flex items-start gap-4">
            <x-marketplace::resource-icon :resource="$resource" size="md" />
            <div class="min-w-0 flex-1">
                <div class="mb-1 flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">{{ $resource->category?->name }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $resource->isFree() ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200' }}">{{ $resource->formattedPrice() }}</span>
                    @if($resource->isFeaturedNow())
                        <span class="rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700 dark:bg-violet-900/30 dark:text-violet-200">Featured</span>
                    @endif
                    @if($resource->is_official)
                        <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 dark:bg-sky-900/30 dark:text-sky-200">Official</span>
                    @endif
                </div>
                <h3 class="truncate text-base font-semibold text-gray-900 group-hover:text-primary-700 dark:text-white dark:group-hover:text-primary-300">{{ $resource->name }}</h3>
                <p class="mt-1 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">{{ $resource->short_description }}</p>
                @if($resource->reviews_count > 0)
                    <div class="mt-2">
                        <x-marketplace::star-rating :rating="$resource->reviews_avg" :count="$resource->reviews_count" />
                    </div>
                @endif
            </div>
        </div>
    </a>
    <div class="mt-4 flex items-center justify-between gap-3 border-t border-gray-100 pt-3 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
        <div class="min-w-0">
            @if($resource->author)
                <x-marketplace::author-link :user="$resource->author" size="sm" />
            @endif
        </div>
        <div class="flex shrink-0 flex-wrap justify-end gap-3">
            <span>{{ number_format($resource->views_count) }} views</span>
            <span>{{ number_format($resource->downloads_count) }} downloads</span>
            <span>{{ number_format($resource->purchases_count) }} purchases</span>
            @if($latest)
                <span>v{{ $latest->version }}</span>
            @endif
        </div>
    </div>
</div>
