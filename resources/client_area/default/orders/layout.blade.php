@extends('theme::layouts.wrapper', [
    'activePage' => 'dashboard',
])

@section('title', 'View Order')

@if(in_array($order->status, ['pending', 'processing', 'failed']))
    @section('content')
        @livewire(client_view_path('orders.livewire.waiting-screen'), ['order' => $order])
    @endsection

@else
    @section('content')
        <div class="mx-auto max-w-screen-2xl px-4 2xl:px-0">
            <div class="flex flex-wrap">
                <div class="w-full pl-4 pl-4 pl-4 pr-4 pr-4 pr-4 sm:w-1/2 md:w-1/3 lg:w-1/4">
                    <x-theme::card class="mb-4 p-2">
                        <x-theme::navlist.list>
                            <x-theme::navlist.item wire:navigate text="General" href="{{ route('orders.view', $order->id) }}" :active="$activeTab == 'general'">
                                <x-slot:icon>
                                    <svg aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                                        </path>
                                    </svg>
                                </x-slot:icon>
                            </x-theme::navlist.item>

                            <x-theme::navlist.item wire:navigate text="Payments" href="{{ route('orders.view.payments', ['order' => $order->id]) }}" :active="$activeTab == 'payments'">
                                <x-slot:icon>
                                    <svg aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M8.707 7.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l2-2a1 1 0 00-1.414-1.414L11 7.586V3a1 1 0 10-2 0v4.586l-.293-.293z">
                                        </path>
                                        <path d="M3 5a2 2 0 012-2h1a1 1 0 010 2H5v7h2l1 2h4l1-2h2V5h-1a1 1 0 110-2h1a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V5z">
                                        </path>
                                    </svg>
                                </x-slot:icon>
                            </x-theme::navlist.item>

                            @if($order->isRecurring() AND \App\Models\GatewayConfig::where('type', 'subscription')->where('is_active', true)->count() > 0 && \App\Support\LicensePlanLimits::subscriptionsEnabled())
                            <x-theme::navlist.item wire:navigate text="Subscription" href="{{ route('orders.view.subscription', ['order' => $order->id]) }}" :active="$activeTab == 'subscription'">
                                <x-slot:icon>
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.651 7.65a7.131 7.131 0 0 0-12.68 3.15M18.001 4v4h-4m-7.652 8.35a7.13 7.13 0 0 0 12.68-3.15M6 20v-4h4"/>
                                    </svg>
                                </x-slot:icon>
                            </x-theme::navlist.item>
                            @endif

                            <x-theme::navlist.item wire:navigate text="Emails" href="{{ route('orders.view.emails', ['order' => $order->id]) }}" :active="$activeTab == 'emails'">
                                <x-slot:icon>
                                    <svg fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 flex-shrink-0 text-gray-500 transition duration-75 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white" aria-hidden="true">
                                        <path d="M2.038 5.61A2.01 2.01 0 0 0 2 6v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6c0-.120-.010-.238-.030-.352l-.866.65-7.89 6.032a2 2 0 0 1-2.429 0L2.884 6.288l-.846-.677Z"/>
                                        <path d="M20.677 4.117A1.996 1.996 0 0 0 20 4H4c-.225 0-.44.037-.642.105l.758.607L12 10.742 19.9 4.7l.777-.583Z"/>
                                    </svg>
                                </x-slot:icon>
                            </x-theme::navlist.item>

                            <x-theme::navlist.item wire:navigate text="Members" href="{{ route('orders.view.members', ['order' => $order->id]) }}" :active="$activeTab == 'members'">
                                <x-slot:icon>
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd" d="M12 6a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Zm-1.5 8a4 4 0 0 0-4 4 2 2 0 0 0 2 2h7a2 2 0 0 0 2-2 4 4 0 0 0-4-4h-3Zm6.82-3.096a5.51 5.51 0 0 0-2.797-6.293 3.5 3.5 0 1 1 2.796 6.292ZM19.5 18h.5a2 2 0 0 0 2-2 4 4 0 0 0-4-4h-1.1a5.503 5.503 0 0 1-.471.762A5.998 5.998 0 0 1 19.5 18ZM4 7.5a3.5 3.5 0 0 1 5.477-2.889 5.5 5.5 0 0 0-2.796 6.293A3.501 3.501 0 0 1 4 7.5ZM7.1 12H6a4 4 0 0 0-4 4 2 2 0 0 0 2 2h.5a5.998 5.998 0 0 1 3.071-5.238A5.505 5.505 0 0 1 7.1 12Z" clip-rule="evenodd"/>
                                    </svg>
                                </x-slot:icon>
                            </x-theme::navlist.item>
                        </x-theme::navlist.list>
                    </x-theme::card>

                    @foreach(extensionElements(['client-order-sidebar-bottom-view']) as $element)
                        @includeIf($element['view'], ['order' => $order])
                    @endforeach
                </div>
                <div class="w-full pl-4 pl-4 pl-4 pr-4 pr-4 pr-4 sm:w-1/2 md:w-2/3 lg:w-3/4">
                    @yield('container')
                </div>
            </div>

        @livewire(client_view_path('orders.livewire.renew-order-drawer'), ['order' => $order])
    @endsection
@endif
