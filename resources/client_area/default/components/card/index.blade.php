@props([

])

<div {{ $attributes->class(["p-4 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700 sm:p-6"])->merge([]) }}>
    {{ $slot }}
</div>
