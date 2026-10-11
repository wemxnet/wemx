@props([
    'icon' => '',
    'title' => '',
    'description' => '',
])

<div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6">
    <div class="mb-2 flex h-11 w-11 items-center justify-center rounded-lg bg-primary-600 text-primary-700 dark:bg-primary-900 dark:text-primary-400 sm:h-12 sm:w-12">
        {!! $icon !!}
    </div>
    <div class="min-w-0">
        <h2 class="break-words text-base font-bold text-gray-900 dark:text-white sm:text-lg">{{ $title }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
    </div>
</div>
