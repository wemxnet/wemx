@props([
    'rating' => 0,
    'count' => null,
    'size' => 'sm',
])

@php
    $rating = (float) $rating;
    $sizes = [
        'sm' => 'h-4 w-4',
        'md' => 'h-5 w-5',
    ];
    $icon = $sizes[$size] ?? $sizes['sm'];
@endphp

<div {{ $attributes->class('inline-flex items-center gap-1 text-amber-500') }}>
    @for($i = 1; $i <= 5; $i++)
        <svg class="{{ $icon }}" viewBox="0 0 20 20" fill="{{ $rating >= $i - 0.25 ? 'currentColor' : 'none' }}" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
        </svg>
    @endfor
    @if($count !== null)
        <span class="ml-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($rating, 1) }} ({{ $count }})</span>
    @endif
</div>
