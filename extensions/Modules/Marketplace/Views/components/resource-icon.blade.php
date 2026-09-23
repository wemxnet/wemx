@props([
    'resource',
    'size' => 'md',
])

@php
    /** @var \Extensions\Modules\Marketplace\Models\MarketplaceResource $resource */
    $sizes = [
        'sm' => 'h-10 w-10 text-xs rounded-lg',
        'md' => 'h-14 w-14 text-sm rounded-xl',
        'lg' => 'h-16 w-16 text-base rounded-2xl',
    ];
    $box = $sizes[$size] ?? $sizes['md'];
@endphp

<div {{ $attributes->class("flex shrink-0 items-center justify-center overflow-hidden border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900 {$box}") }}>
    @if($resource->iconUrl())
        <img src="{{ $resource->iconUrl() }}" alt="" class="h-full w-full object-cover">
    @else
        <span
            class="font-semibold tracking-wide text-primary-700 dark:text-primary-300"
            title="{{ $resource->name }} [{{ $resource->initials() }}]"
        >[{{ $resource->initials() }}]</span>
    @endif
</div>
