@extends('theme::layouts.wrapper', [
    'activePage' => 'marketplace',
])

@php
    $activeTab = $activeTab ?? 'resource';
    $resource = $resource ?? null;
@endphp

@section('title', $resource?->name ?? 'Create resource')

@section('content')
    <div class="mx-auto max-w-screen-2xl px-4 2xl:px-0">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3 px-4">
            <div>
                <a href="{{ route('marketplace.studio.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">Back to studio</a>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $resource?->name ?? 'Publish a resource' }}</h1>
                @if($resource)
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $resource->status->label() }}
                        @if($resource->is_disabled)
                            · Disabled (hidden from marketplace)
                        @endif
                        @if($resource->is_official)
                            · Official
                        @endif
                    </p>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Listing details first, then the initial version.</p>
                @endif
            </div>
            @if($resource)
                <a href="{{ $resource->clientUrl() }}" wire:navigate class="text-sm text-primary-700 hover:underline dark:text-primary-300">View listing</a>
            @endif
        </div>

        <div class="flex flex-wrap">
            <div class="w-full px-4 sm:w-1/2 md:w-1/3 lg:w-1/4">
                <x-theme::card class="mb-4 p-2">
                    <x-theme::navlist.list>
                        @include('marketplace::client_area.default.marketplace.partials.studio-resource-nav-item', [
                            'text' => 'Resource',
                            'active' => $activeTab === 'resource',
                            'href' => $resource?->studioUrl(),
                            'enabled' => true,
                            'icon' => 'resource',
                        ])
                        @include('marketplace::client_area.default.marketplace.partials.studio-resource-nav-item', [
                            'text' => 'Versions',
                            'active' => $activeTab === 'versions',
                            'href' => $resource?->studioUrl('versions'),
                            'enabled' => (bool) $resource,
                            'icon' => 'versions',
                        ])
                        @if($resource && ! $resource->isFree())
                            @include('marketplace::client_area.default.marketplace.partials.studio-resource-nav-item', [
                                'text' => 'Purchases',
                                'active' => $activeTab === 'licenses',
                                'href' => $resource->studioUrl('licenses'),
                                'enabled' => true,
                                'icon' => 'licenses',
                            ])
                        @endif
                        @include('marketplace::client_area.default.marketplace.partials.studio-resource-nav-item', [
                            'text' => 'Team',
                            'active' => $activeTab === 'team',
                            'href' => $resource?->studioUrl('team'),
                            'enabled' => (bool) $resource,
                            'icon' => 'team',
                        ])
                    </x-theme::navlist.list>
                </x-theme::card>
            </div>
            <div class="w-full px-4 sm:w-1/2 md:w-2/3 lg:w-3/4">
                @yield('container')
            </div>
        </div>
    </div>
@endsection
