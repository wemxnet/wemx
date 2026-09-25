@php
    $size = (int) ($size ?? 48);
    $initials = $resource['initials'] ?? 'R';
@endphp

<div
    class="flex-shrink-0 d-inline-flex align-items-center justify-content-center overflow-hidden rounded border bg-white"
    style="width: {{ $size }}px; height: {{ $size }}px; min-width: {{ $size }}px;"
>
    @if(! empty($resource['icon']))
        <img src="{{ $resource['icon'] }}" alt="" style="width: 100%; height: 100%; object-fit: contain; padding: 4px;">
    @else
        <span class="fw-bold text-primary" style="font-size: {{ max(12, (int) round($size / 3)) }}px; line-height: 1;">{{ $initials }}</span>
    @endif
</div>
