<x-mail::message>
# Hi {{ $name ?? 'there' }},

{{ $body }}

@if($markdownTable)
<x-mail::table>
{{ $markdownTable }}
</x-mail::table>
@endif

@if(! empty($button['url']))
<x-mail::button :url="$button['url']">
{{ $button['text'] ?? 'Learn more' }}
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
