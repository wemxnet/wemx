@props([
    'text' => null,
    'active' => false,
    'iconBase' => 'h-6 w-6 flex-shrink-0 text-gray-500 transition duration-75 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white',
])


<a {{ $attributes->class(['flex items-center rounded-lg p-2 text-gray-900 dark:text-white', $active ? 'bg-gray-100 dark:bg-gray-700' : 'hover:bg-gray-100 dark:hover:bg-gray-700'])->merge([]) }}>
    <span class="{{ $iconBase }} inline-flex items-center justify-center overflow-hidden [&>svg]:h-6 [&>svg]:w-6">
        {{ $icon ?? '' }}
    </span>
    <span class="ml-3 flex-1 whitespace-nowrap">
        {{ $text ?? $slot }}
    </span>
</a>
