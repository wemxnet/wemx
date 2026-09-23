@props([])

<input
    {{ $attributes->class([
        'block w-full cursor-pointer rounded-lg border border-gray-300 bg-gray-50 text-sm text-gray-700',
        'focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30',
        'file:mr-4 file:cursor-pointer file:rounded-l-lg file:border-0 file:bg-gray-200 file:px-4 file:py-2.5',
        'file:text-sm file:font-medium file:text-gray-800 hover:file:bg-gray-300',
        'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200',
        'dark:file:bg-gray-600 dark:file:text-white dark:hover:file:bg-gray-500',
    ])->merge([]) }}
    type="file"
>
