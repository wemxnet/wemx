@php
    $size = $size ?? 48;
    $initials = $resource['initials'] ?? 'R';
@endphp

<div class="avatar" style="width: {{ $size }}px; height: {{ $size }}px; background: var(--tblr-bg-surface-secondary);">
    @if(! empty($resource['icon']))
        <img src="{{ $resource['icon'] }}" alt="" class="rounded" style="width: {{ $size }}px; height: {{ $size }}px; object-fit: cover;">
    @else
        <span class="fw-bold text-primary">{{ $initials }}</span>
    @endif
</div>
