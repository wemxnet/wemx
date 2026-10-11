@props([
    'activePage' => '',
])

<header>
    <nav
        class="bg-white border-b border-gray-200 px-4 py-2.5 dark:bg-gray-800 dark:border-gray-700"
    >
        <div class="flex flex-nowrap items-center justify-between gap-2">
            <div class="flex min-w-0 items-center gap-1 sm:gap-2">
                <button
                    type="button"
                    id="client-nav-toggle"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg p-2 text-sm text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 lg:hidden dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600"
                    aria-controls="client-main-navigation"
                    aria-expanded="false"
                    data-collapse-toggle="client-main-navigation"
                >
                    <span class="sr-only">Open main menu</span>
                    <svg class="w-6 h-6 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 17 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 1h15M1 7h15M1 13h15"/>
                    </svg>
                </button>
                <a href="/" class="flex min-w-0 items-center">
                    <img
                        src="{{ settings('app_logo', '/assets/common/img/app-logo.png') }}"
                        class="h-8 w-8 shrink-0 rounded sm:mr-2 sm:h-10 sm:w-auto"
                        alt="Application Logo"
                    />
                    <span class="hidden truncate text-lg font-semibold dark:text-white sm:inline sm:text-2xl">{{ settings('app_name', 'Application') }}</span>
                </a>
            </div>
            <div class="flex shrink-0 items-center lg:order-2">
                <button data-tooltip-target="tooltip-dark" type="button" onclick="toggleDarkmode()" class="inline-flex h-10 w-10 items-center justify-center rounded-lg p-2 text-sm font-medium text-gray-500 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-300 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-800">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                    </svg>
                </button>
                <span class="hidden mx-2 w-px h-5 bg-gray-200 dark:bg-gray-600 lg:inline"></span>
                @livewire(client_view_path('livewire.widgets.currency-dropdown'))
                <span class="hidden mx-2 w-px h-5 bg-gray-200 dark:bg-gray-600 lg:inline"></span>
                @auth
                <a href="{{ route('dashboard.email-inbox') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-lg p-2 text-sm font-medium text-gray-500 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-300 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-800 @if(request()->routeIs('dashboard.email-inbox')) bg-gray-100 dark:bg-gray-700 @endif" data-tooltip-target="tooltip-email" wire:navigate>
                    <div class="relative">
                        <svg class="w-6 h-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M2.038 5.61A2.01 2.01 0 0 0 2 6v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6c0-.12-.01-.238-.03-.352l-.866.65-7.89 6.032a2 2 0 0 1-2.429 0L2.884 6.288l-.846-.677Z"/>
                            <path d="M20.677 4.117A1.996 1.996 0 0 0 20 4H4c-.225 0-.44.037-.642.105l.758.607L12 10.742 19.9 4.7l.777-.583Z"/>
                        </svg>
                        @if($unreadEmailCount = auth()->user()->emails()->where('seen_at', null)->count())
                            <div class="absolute inline-flex items-center justify-center w-4 h-4 text-xs font-medium text-white bg-red-700 rounded-full -top-1.5 -end-1.5 dark:bg-red-600">{{ $unreadEmailCount }}</div>
                        @endif
                    </div>
                </a>
                <span class="hidden mx-2 w-px h-5 bg-gray-200 dark:bg-gray-600 lg:inline"></span>
                @endauth
                @livewire(client_view_path('livewire.widgets.cart-nav-dropdown'))
                @auth
                <button
                    type="button"
                    class="mx-1 flex rounded-full bg-gray-800 text-sm focus:ring-4 focus:ring-gray-300 sm:mx-2 md:mr-0 dark:focus:ring-gray-600"
                    id="user-menu-button"
                    aria-expanded="false"
                    data-dropdown-toggle="dropdown"
                >
                    <span class="sr-only">Open user menu</span>
                    <img
                        class="w-8 h-8 rounded-full"
                        src="{{ auth()->user()->getAvatarUrl() }}"
                        alt="user photo"
                    />
                </button>
                <!-- Dropdown menu -->
                <div
                    class="hidden z-50 my-4 w-56 text-base list-none bg-white rounded divide-y divide-gray-100 shadow dark:bg-gray-700 dark:divide-gray-600 rounded-xl"
                    id="dropdown"
                >
                    <div class="py-3 px-4">
              <span
                  class="block text-sm font-semibold text-gray-900 dark:text-white"
              >{{ auth()->user()->full_name }}</span
              >
                        <span
                            class="block text-sm text-gray-900 truncate dark:text-white"
                        >{{ auth()->user()->email }}</span
                        >
                    </div>
                    <ul
                        class="py-1 text-gray-700 dark:text-gray-300"
                        aria-labelledby="dropdown"
                    >
                        <li>
                            <a
                                href="{{ route('account.settings') }}" wire:navigate
                                class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white"
                            >Account settings</a
                            >
                        </li>
                        @foreach(extensionElements('client-dropdown-item') as $element)
                            <li>
                                <a
                                    href="{{ $element['attributes']['href'] }}" @isset($element['attributes']['target']) target="{{ $element['attributes']['target'] }}" @endisset @isset($element['attributes']['navigate']) wire:navigate @endisset
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white"
                                >{{ $element['attributes']['name'] }}</a
                                >
                            </li>
                        @endforeach
                        @if(auth()->user()->isStaff())
                        <li>
                            <a
                                href="{{ route('admin.index') }}"
                                class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-red-500 dark:hover:text-red-400"
                            >Admin Dashboard</a
                            >
                        </li>
                        @endif
                    </ul>
                    <ul
                        class="py-1 text-gray-700 dark:text-gray-300"
                        aria-labelledby="dropdown"
                    >
                        <li>
                            <a
                                href="{{ route('subscriptions.index') }}" wire:navigate
                                class="flex items-center py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white"
                            ><svg
                                    aria-hidden="true"
                                    class="mr-2 w-5 h-5 text-primary-600 dark:text-primary-500"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z"
                                        clip-rule="evenodd"
                                    ></path>
                                </svg>
                                Subscriptions</a
                            >
                        </li>
                    </ul>
                    <ul
                        class="py-1 text-gray-700 dark:text-gray-300"
                        aria-labelledby="dropdown"
                    >
                        <li>
                            <a
                                href="{{ route('logout') }}"
                                class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white"
                            >Sign out</a
                            >
                        </li>
                    </ul>
                </div>
                @endauth
                @guest
                <div class="hidden items-center gap-1 min-[380px]:flex">
                    <a href="{{ route('login') }}" class="rounded-lg px-2.5 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-300 dark:text-white dark:hover:bg-gray-700 dark:focus:ring-gray-800 sm:px-4 lg:py-2.5">Login</a>
                    @if(settings('enable_registrations', true))
                        <a href="{{ route('register') }}" class="bg-primary-700 hover:bg-primary-800 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800 rounded-lg px-2.5 py-2 text-sm font-medium text-white focus:outline-none focus:ring-4 sm:px-4 lg:py-2.5">Sign up</a>
                    @endif
                </div>
                @endguest
            </div>
        </div>
    </nav>
</header>

@if(session('impersonate'))
    <div>
        <x-theme::alert.warning class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between" style="margin-bottom: 0px;">
            <span class="min-w-0">
                You are currently logged in as {{ auth()->user()->username }} ({{ Str::mask(auth()->user()->email, '*', 3, strpos(auth()->user()->email, '@') - 3) }}).
            </span>
            <x-theme::button.primary class="w-full justify-center sm:w-auto" href="{{ route('admin.users.exit-impersonate') }}" text="Exit"/>
        </x-theme::alert.warning>
    </div>
@endif

@php
    $clientNavLink = function (bool $active): string {
        $base = 'block rounded-lg px-3 py-2.5 lg:rounded-none lg:bg-transparent lg:px-4 lg:py-3';

        if ($active) {
            return $base.' bg-white font-medium text-primary-700 dark:bg-gray-800 dark:text-primary-400 lg:border-b-2 lg:border-primary-600';
        }

        return $base.' text-gray-700 hover:bg-white dark:text-gray-300 dark:hover:bg-gray-800 lg:text-gray-500 lg:hover:border-b-2 lg:hover:border-primary-600 lg:hover:text-primary-600 dark:lg:text-gray-400 dark:lg:hover:border-primary-500 dark:lg:hover:text-primary-500';
    };
@endphp

<div class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900 lg:border-b">
    <div
        id="client-main-navigation"
        class="hidden w-full border-t border-gray-200 px-2 py-2 dark:border-gray-700 lg:flex lg:flex-row lg:items-center lg:justify-center lg:border-t-0 lg:px-4 lg:py-3"
    >
        <nav
            class="w-full lg:bg-transparent"
            aria-label="Main navigation"
        >
            <ul class="flex w-full flex-col gap-1 text-sm font-medium lg:flex-row lg:flex-wrap lg:justify-center lg:gap-1">
                <li>
                    <a href="/" wire:navigate class="{{ $clientNavLink($activePage == 'dashboard') }}">
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="{{ route('categories.index') }}" wire:navigate class="{{ $clientNavLink($activePage == 'categories') }}" @if($activePage == 'categories') aria-current="page" @endif>
                        Categories
                    </a>
                </li>
                @foreach(extensionElements(['navigation-item']) as $element)
                    <li>
                        <a href="{{ $element['attributes']['href'] ?? '#' }}" wire:navigate class="{{ $clientNavLink($activePage == ($element['attributes']['active'] ?? null)) }}">
                            {{ $element['attributes']['name'] ?? 'undefined' }}
                        </a>
                    </li>
                @endforeach
                @guest
                    <li class="mt-1 border-t border-gray-200 pt-1 dark:border-gray-700 min-[380px]:hidden lg:hidden">
                        <a href="{{ route('login') }}" class="{{ $clientNavLink(false) }}">Login</a>
                    </li>
                    @if(settings('enable_registrations', true))
                        <li class="min-[380px]:hidden lg:hidden">
                            <a href="{{ route('register') }}" class="{{ $clientNavLink(false) }}">Sign up</a>
                        </li>
                    @endif
                @endguest
            </ul>
        </nav>
    </div>
</div>
