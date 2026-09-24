@php
    $rating = (float) ($rating ?? 0);
    $count = $count ?? null;
@endphp

<span class="d-inline-flex align-items-center gap-1 text-yellow">
    @for($i = 1; $i <= 5; $i++)
        <i class="ti {{ $rating >= $i - 0.25 ? 'ti-star-filled' : 'ti-star' }}"></i>
    @endfor
    @if($count !== null)
        <span class="text-secondary small ms-1">{{ number_format($rating, 1) }} ({{ $count }})</span>
    @endif
</span>
