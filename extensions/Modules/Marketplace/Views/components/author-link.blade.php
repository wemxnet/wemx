@props([
    'user',
    'role' => null,
    'size' => 'md',
])

@php
    /** @var \App\Models\User $user */
    $avatar = $size === 'sm' ? 'h-6 w-6' : 'h-10 w-10';
    $nameClass = $size === 'sm'
        ? 'text-xs font-medium text-gray-700 hover:text-primary-700 dark:text-gray-200 dark:hover:text-primary-300'
        : 'text-sm font-medium text-gray-900 hover:text-primary-700 dark:text-white dark:hover:text-primary-300';
    $profileUrl = \Extensions\Modules\Marketplace\Models\MarketplaceResource::authorProfileUrl($user);
@endphp

<div
    {{ $attributes->class('relative inline-flex max-w-full') }}
    x-data="{ open: false, timer: null }"
    @mouseenter="clearTimeout(timer); open = true"
    @mouseleave="timer = setTimeout(() => open = false, 160)"
    @focusin="open = true"
    @focusout="timer = setTimeout(() => open = false, 160)"
>
    <a href="{{ $profileUrl }}" wire:navigate class="inline-flex min-w-0 items-center gap-2">
        <img src="{{ $user->getAvatarUrl() }}" alt="" class="{{ $avatar }} shrink-0 rounded-full">
        <span class="min-w-0">
            <span class="{{ $nameClass }} block truncate">{{ $user->username }}</span>
            @if($role)
                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $role }}</span>
            @endif
        </span>
    </a>

    <div
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1"
        class="absolute left-0 top-full z-30 mt-2 w-64 rounded-xl border border-gray-200 bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-800"
        @mouseenter="clearTimeout(timer); open = true"
        @mouseleave="timer = setTimeout(() => open = false, 160)"
    >
        <div class="flex items-center gap-3">
            <img src="{{ $user->getAvatarUrl() }}" alt="" class="h-12 w-12 rounded-full">
            <div class="min-w-0">
                <div class="truncate font-semibold text-gray-900 dark:text-white">{{ $user->username }}</div>
                @if($role)
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $role }}</div>
                @endif
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Browse resources they authored or collaborated on.</p>
        <a
            href="{{ $profileUrl }}"
            wire:navigate
            class="mt-3 inline-flex w-full items-center justify-center rounded-lg bg-primary-700 px-3 py-2 text-sm font-medium text-white hover:bg-primary-800 dark:bg-primary-600 dark:hover:bg-primary-500"
        >View profile</a>
    </div>
</div>
