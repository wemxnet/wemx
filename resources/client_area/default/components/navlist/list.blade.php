@props(['class' => 'space-y-2'])

<ul {{ $attributes->merge(['class' => 'font-medium '.$class]) }}>
    {{ $slot }}
</ul>
