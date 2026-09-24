@props([
    'resource',
    'size' => 'md',
])

@php
    /** @var \Extensions\Modules\Marketplace\Models\MarketplaceResource $resource */
    $sizes = [
        'sm' => 'h-10 w-10 text-xs rounded-lg p-1',
        'md' => 'h-14 w-14 text-sm rounded-xl p-1.5',
        'lg' => 'h-16 w-16 text-base rounded-2xl p-2',
    ];
    $box = $sizes[$size] ?? $sizes['md'];
@endphp

<div {{ $attributes->class("flex shrink-0 items-center justify-center overflow-hidden border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 {$box}") }}>
    @if($resource->iconUrl())
        <img src="{{ $resource->iconUrl() }}" alt="" class="h-full w-full object-contain" loading="lazy">
    @else
        <span
            class="font-semibold tracking-wide text-primary-700 dark:text-primary-300"
            title="{{ $resource->name }}"
        >{{ $resource->initials() }}</span>
    @endif
</div>
