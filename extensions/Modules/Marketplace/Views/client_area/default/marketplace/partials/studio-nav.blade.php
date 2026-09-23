@php
    $links = [
        ['route' => 'marketplace.studio.index', 'label' => 'My resources'],
        ['route' => 'marketplace.studio.sales', 'label' => 'Sales'],
        ['route' => 'marketplace.studio.licenses', 'label' => 'Purchases'],
        ['route' => 'marketplace.studio.gateways', 'label' => 'Payment methods'],
    ];
@endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <nav class="flex flex-wrap gap-2">
        @foreach($links as $link)
            <a
                href="{{ route($link['route']) }}"
                wire:navigate
                class="rounded-full px-3 py-1.5 text-sm font-medium {{ request()->routeIs($link['route']) ? 'bg-primary-700 text-white dark:bg-primary-600' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}"
            >
                {{ $link['label'] }}
            </a>
        @endforeach
    </nav>
    <x-theme::button.primary href="{{ route('marketplace.studio.create') }}" wire:navigate>
        New resource
    </x-theme::button.primary>
</div>
