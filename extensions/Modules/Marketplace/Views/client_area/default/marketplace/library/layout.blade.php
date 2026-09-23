@extends('theme::layouts.wrapper', [
    'activePage' => 'marketplace',
])

@php
    $activeTab = $activeTab ?? 'purchases';
@endphp

@section('title', $activeTab === 'resources' ? 'My Resources' : 'My Purchases')

@section('content')
    <div class="mx-auto max-w-screen-2xl px-4 2xl:px-0">
        <div class="mb-4 px-4">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $activeTab === 'resources' ? 'My Resources' : 'My Purchases' }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $activeTab === 'resources' ? 'Resources you publish or help manage.' : 'Resources you have purchased or been granted access to.' }}
            </p>
        </div>

        <div class="flex flex-wrap">
            <div class="w-full px-4 sm:w-1/2 md:w-1/3 lg:w-1/4">
                <x-theme::card class="mb-4 p-2">
                    <x-theme::navlist.list>
                        <x-theme::navlist.item wire:navigate text="My Purchases" href="{{ route('marketplace.library.purchases') }}" :active="$activeTab === 'purchases'">
                            <x-slot:icon>
                                <svg aria-hidden="true" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6">
                                    <path fill-rule="evenodd" d="M15.75 1.5a6.75 6.75 0 0 0-6.651 7.906c.067.39-.032.717-.221.906l-6.5 6.499a3 3 0 0 0-.788 3.277l.528 1.321a3 3 0 0 0 2.795 1.841H8.25a.75.75 0 0 0 0-1.5H5.414a1.5 1.5 0 0 1-1.398-.92l-.527-1.322a1.5 1.5 0 0 1 .394-1.638l6.5-6.5c.19-.189.517-.288.906-.22A6.75 6.75 0 1 0 15.75 1.5Zm0 3a.75.75 0 0 0 0 1.5A1.5 1.5 0 0 1 17.25 7.5a.75.75 0 0 0 1.5 0 3 3 0 0 0-3-3Z" clip-rule="evenodd"/>
                                </svg>
                            </x-slot:icon>
                        </x-theme::navlist.item>

                        <x-theme::navlist.item wire:navigate text="My Resources" href="{{ route('marketplace.library.resources') }}" :active="$activeTab === 'resources'">
                            <x-slot:icon>
                                <svg aria-hidden="true" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6">
                                    <path d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
                                </svg>
                            </x-slot:icon>
                        </x-theme::navlist.item>
                    </x-theme::navlist.list>
                </x-theme::card>

                <x-theme::card class="mb-4 p-3">
                    <a href="{{ route('marketplace.index') }}" wire:navigate class="block text-sm text-primary-700 hover:underline dark:text-primary-300">Browse marketplace</a>
                    <a href="{{ route('marketplace.studio.index') }}" wire:navigate class="mt-2 block text-sm text-primary-700 hover:underline dark:text-primary-300">Creator studio</a>
                </x-theme::card>
            </div>
            <div class="w-full px-4 sm:w-1/2 md:w-2/3 lg:w-3/4">
                @yield('container')
            </div>
        </div>
    </div>
@endsection
