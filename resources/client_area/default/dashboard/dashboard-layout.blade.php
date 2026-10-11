@extends('theme::layouts.wrapper', [
    'activePage' => 'dashboard',
])

@section('title', 'Dashboard')

@section('content')
@php
    $dashboardUser = auth()->user();
    $activeSubscriptionsCount = $dashboardUser->subscriptions()->where(function ($query) {
        $query->where('status', 'active')
            ->orWhere(function ($q) {
                $q->where('status', 'cancelled')
                    ->whereNotNull('next_billing_at')
                    ->where('next_billing_at', '>', now());
            });
    })->count();
    $totalSubscriptionsCount = $dashboardUser->subscriptions()->count();
@endphp
<div class="mx-auto max-w-screen-2xl px-0 sm:px-4 2xl:px-0">
    <div class="flex flex-col gap-4 lg:flex-row">
        <div class="w-full lg:w-1/4">

            <!-- Sidebar Widgets -->
            <div class="gap-4 rounded-lg border border-gray-200 bg-white p-4 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 mb-4">
                <div class="mb-6 flex items-center gap-4">
                    <img class="h-14 w-14 shrink-0 rounded-lg sm:h-16 sm:w-16" src="{{ auth()->user()->getAvatarUrl() }}" alt="{{ auth()->user()->full_name }}">
                    <div class="min-w-0">
                        <h2 class="break-words text-xl font-bold leading-tight text-gray-900 dark:text-white">{{ auth()->user()->full_name }}</h2>
                    </div>
                </div>
                <dl class="mb-4">
                    <dt class="mb-2 font-semibold leading-none text-gray-900 dark:text-white">Email Adress</dt>
                    <dd class="break-all text-gray-500 dark:text-gray-400">{{ auth()->user()->email }}</dd>
                    <dd>
                        <x-theme::text.link wire:navigate href="{{ route('account.settings') }}" text="Edit"/>
                    </dd>
                </dl>
                <dl class="mb-4">
                    <dt class="mb-2 font-semibold leading-none text-gray-900 dark:text-white">Account Balance</dt>
                    <dd class="text-gray-500 dark:text-gray-400">{{ price(auth()->user()->balance) }}</dd>
                    <dd>
                        <x-theme::text.link href="#" text="Add Balance" data-drawer-target="add-balance-drawer" data-drawer-show="add-balance-drawer" data-drawer-placement="right" aria-controls="add-balance-drawer"/>
                    </dd>
                </dl>
                <dl class="mb-4">
                    <dt class="mb-2 font-semibold leading-none text-gray-900 dark:text-white">Two Factor Authentication</dt>
                    @if(auth()->user()->tfa_enabled)
                    <dd class="text-gray-500 dark:text-gray-400 mb-2">
                        <x-theme::badge.success text="Enabled"/>
                    </dd>
                    <dd>
                        <x-theme::text.link href="{{ route('disable-2fa') }}" wire:navigate text="Disable"/>
                    </dd>
                    @else
                    <dd class="text-gray-500 dark:text-gray-400 mb-2">
                        <x-theme::badge.danger text="Disabled"/>
                    </dd>
                    <dd>
                        <x-theme::text.link href="{{ route('enable-2fa') }}" wire:navigate text="Enable"/>
                    </dd>
                    @endif
                </dl>
            </div>

            @livewire(client_view_path('dashboard.livewire.add-balance-drawer'))

            <!-- Sidebar Widgets -->
            @foreach(extensionElements(['client-dashboard-sidebar-view']) as $element)
                @includeIf($element['view'], ['user' => auth()->user()])
            @endforeach
        </div>
        <div class="min-w-0 w-full lg:w-3/4">
            <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-theme::stat title="{{ auth()->user()->orders()->whereStatus('active')->count() }} active orders" description="{{ auth()->user()->orders()->whereStatus('suspended')->count() }} suspended, {{ auth()->user()->orders()->whereStatus('terminated')->count() }} terminated" icon='<svg class="w-6 h-6 text-primary-300" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M20 10H4v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8ZM9 13v-1h6v1a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1Z" clip-rule="evenodd"/><path d="M2 6a2 2 0 0 1 2-2h16a2 2 0 1 1 0 4H4a2 2 0 0 1-2-2Z"/></svg>' />
                <x-theme::stat :title="price(auth()->user()->balance)" description="Available Account balance" icon='<svg class="w-6 h-6 text-primary-300" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 14h2m3 0h4m2 2h2m0 0h2m-2 0v2m0-2v-2m-5 4H4c-.55228 0-1-.4477-1-1V7c0-.55228.44772-1 1-1h16c.5523 0 1 .44772 1 1v4M3 10h18"/></svg>' />
                <x-theme::stat title="{{ $activeSubscriptionsCount }} active subscriptions" description="{{ $totalSubscriptionsCount }} total subscriptions" icon='<svg class="w-6 h-6 text-primary-300" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>' />
                <x-theme::stat title="{{ auth()->user()->payments()->whereStatus('paid')->count() }} paid payments" description="3 invoices in total" icon='<svg class="w-6 h-6 text-primary-300" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M8 7V6a1 1 0 0 1 1-1h11a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1h-1M3 18v-7a1 1 0 0 1 1-1h11a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Zm8-3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/></svg>' />
            </div>

            @foreach(extensionElements(['client-dashboard-top-view']) as $element)
                @includeIf($element['view'], ['user' => auth()->user()])
            @endforeach

            @yield('container')

            @foreach(extensionElements(['client-dashboard-bottom-view']) as $element)
                @includeIf($element['view'], ['user' => auth()->user()])
            @endforeach
        </div>
    </div>
</div>
@endsection
