<footer class="mt-auto w-full border-t border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
    <div class="w-full max-w-screen-xl mx-auto p-4 md:py-8">
        <div class="sm:flex sm:items-center sm:justify-between">
            <a href="/" class="flex items-center mb-4 sm:mb-0 space-x-3 rtl:space-x-reverse" wire:navigate>
                <img src="{{ settings('app_logo', '/assets/common/img/app-logo.png') }}" class="h-7 rounded" alt="{{ settings('app_name', 'WemX') }} Logo" />
                <span class="self-center whitespace-nowrap text-2xl font-semibold text-gray-900 dark:text-white">{{ settings('app_name', 'WemX') }}</span>
            </a>
            <ul class="mb-6 flex flex-wrap items-center text-sm font-medium text-gray-500 dark:text-gray-400 sm:mb-0">
                @foreach(extensionElements(['footer-item']) as $element)
                <li>
                    <a href="{{ $element['attributes']['href'] ?? '#' }}" class="me-4 hover:underline md:me-6" wire:navigate>{{ $element['attributes']['name'] ?? 'undefined' }}</a>
                </li>
                @endforeach
            </ul>
        </div>
        <hr class="my-6 border-gray-200 dark:border-gray-700 sm:mx-auto lg:my-8" />
        <div class="flex flex-col gap-2 text-sm text-gray-500 dark:text-gray-400 sm:flex-row sm:items-center sm:justify-between">
            <span>© {{ now()->year }} {{ settings('app_name', 'WemX') }}. All rights reserved.</span>
            @if(\App\Support\LicensePlanLimits::showPoweredBy())
                <span>Powered by <span class="font-semibold">WemX</span> <span class="text-xs">{{ config('app.version') }}</span></span>
            @endif
        </div>
    </div>
</footer>
