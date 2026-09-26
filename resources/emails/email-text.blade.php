Hi {{ $name ?? 'there' }},

{!! $text !!}
@if (! empty($button['url']))

{{ $button['text'] ?? 'Learn more' }}: {{ $button['url'] }}
@endif

Thanks,
{{ config('app.name') }}
