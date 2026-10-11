@props([
    'cols' => 3,   // number of columns
    'gap'  => 4,   // Tailwind gap size
])

@php
    $gapClass = match ((int) $gap) {
        2 => 'gap-2',
        6 => 'gap-6',
        8 => 'gap-8',
        default => 'gap-4',
    };
    $colsClass = match ((int) $cols) {
        1 => 'lg:grid-cols-1',
        2 => 'lg:grid-cols-2',
        4 => 'lg:grid-cols-4',
        default => 'lg:grid-cols-3',
    };
@endphp

<div {{ $attributes->merge([
    'class' => "mb-4 mt-4 grid grid-cols-1 {$gapClass} sm:grid-cols-2 {$colsClass}"
]) }}>
    {{ $slot }}
</div>
