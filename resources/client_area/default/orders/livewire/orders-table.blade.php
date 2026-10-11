<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;

new class extends Component
{
    #[Url('filterOrderStatus')]
    public array $filterStatus = [];

    #[Url('orderSearch')]
    public string $search = '';
}

?>

@php
    $user = auth()->user();
    $orders = $user->orders->sortByDesc('created_at');

    // get all available statuses as an array and the count of each status
    $statuses = $orders->pluck('status')->countBy();

    // filter orders by status
    if($this->filterStatus) {
        // make sure the $filterStatus is an array with string values that are in $statuses
        $filterStatus = collect($this->filterStatus)->filter(function($status) use ($statuses) {
            return $statuses->has($status);
        })->toArray();

        // filter orders by status
        $orders = $orders->whereIn('status', $filterStatus);
    }

    // search orders based on package name or category name, and status
    if($this->search) {
        $orders = $orders->filter(function($order) {
            return str_contains(strtolower($order->package->name), strtolower($this->search)) || str_contains(strtolower($order->status), strtolower($this->search)) || str_contains(strtolower($order->package->category->name), strtolower($this->search));
        });
    }
@endphp


<section>
    @if(auth()->user()->orders->isEmpty())
        <x-theme::empty-state
            title="No orders found"
            description="You have not placed any orders yet."
            icon='<svg class="w-8 h-8 text-gray-500 dark:text-gray-400 mb-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h1.5L8 16m0 0h8m-8 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm8 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm.75-3H7.5M11 7H6.312M17 4v6m-3-3h6"/>
            </svg>'
            action-text="Place an order"
            :action-href="route('categories.index')"
            :action-navigate="true"
        />
    @else
    <div class="">
        <!-- Start coding here -->
        <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow-hidden">
            <div class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4 border-b dark:border-gray-700">
                <div class="w-full flex items-center space-x-3">
                    <h5 class="dark:text-white font-semibold">Orders</h5>
                </div>
                <div class="flex w-full flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                    <a href="{{ route('categories.index') }}" wire:navigate class="flex w-full items-center justify-center rounded-lg bg-primary-700 px-3 py-2 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800 sm:w-auto">
                        <svg class="h-3.5 w-3.5 mr-2" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path clip-rule="evenodd" fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                        </svg>
                        New Order
                    </a>

                    <button id="filterDropdownButton" data-dropdown-toggle="filterDropdown" class="flex w-full items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 hover:text-primary-700 focus:z-10 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white dark:focus:ring-gray-700 sm:w-auto" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="h-4 w-4 mr-2 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"></path>
                        </svg>
                        Filter
                        <svg class="-mr-1 ml-1.5 w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path clip-rule="evenodd" fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"></path>
                        </svg>
                    </button>
                    <div id="filterDropdown" class="z-10 w-48 p-3 bg-white rounded-lg shadow dark:bg-gray-700 hidden" style="position: absolute; inset: 0px auto auto 0px; margin: 0px; transform: translate(1148px, 84px);" data-popper-placement="bottom">
                        <h6 class="mb-3 text-sm font-medium text-gray-900 dark:text-white">Filter by status</h6>
                        <ul class="space-y-2 text-sm" aria-labelledby="filterDropdownButton">
                            @foreach($statuses as $status => $count)
                            <li class="flex items-center">
                                <input id="{{ $status }}" wire:model.change="filterStatus" type="checkbox" value="{{ $status }}" class="w-4 h-4 bg-gray-100 border-gray-300 rounded text-primary-600 focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-gray-700 focus:ring-2 dark:bg-gray-600 dark:border-gray-500">
                                <label for="{{ $status }}" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ ucfirst($status) }} ({{ $count }})</label>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="flex w-full items-center sm:max-w-xs sm:flex-1">
                        <label for="order-search" class="sr-only">Search</label>
                        <div class="relative w-full">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <input type="text" wire:model.change="search" id="order-search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="Search" required="">
                        </div>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-4 py-3">
                            <span class="sr-only">Expand/Collapse Row</span>
                        </th>
                        <th scope="col" class="px-4 py-">Product</th>
                        <th scope="col" class="hidden px-4 py-3 sm:table-cell">
                            Price Cycle
                        </th>
                        <th scope="col" class="hidden px-4 py-3 md:table-cell">
                            Members
                        </th>
                        <th scope="col" class="px-4 py-3">
                            Status
                            <svg class="h-4 w-4 ml-1 inline-block" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path clip-rule="evenodd" fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" />
                            </svg>
                        </th>
                        <th scope="col" class="hidden px-4 py-3 sm:table-cell">
                            Due Date
                        </th>
                        <th scope="col" class="hidden px-4 py-3 lg:table-cell">
                            Next Payment
                        </th>
                    </tr>
                    </thead>
                    @foreach($orders as $order)
                    <tbody data-accordion="table-column">
                    <tr class="border-b dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700 cursor-pointer transition" id="table-column-header-{{ $order->id }}" data-accordion-target="#table-column-body-{{ $order->id }}" aria-expanded="false" aria-controls="table-column-body-{{ $order->id }}">
                        <td class="p-3 w-4">
                            <svg data-accordion-icon="" class="w-6 h-6 shrink-0" fill="currentColor" viewbox="0 0 20 20" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </td>
                        <th scope="row" class="px-3 py-3 font-medium text-gray-900 dark:text-white sm:px-4">
                            <div class="flex min-w-0 items-center gap-2">
                                <img class="h-9 w-9 shrink-0 rounded" src="{{ $order->package->icon() }}" alt="">
                                <span class="flex min-w-0 flex-col">
                                    <span class="truncate">{{ $order->package->name }}</span>
                                    <small class="truncate text-gray-500 dark:text-gray-400">
                                        {{ $order->package->category->name }}
                                    </small>
                                </span>
                            </div>
                        </th>
                        <td class="hidden px-4 py-3 sm:table-cell">
                            <div class="flex items-center text-gray-500 dark:text-gray-400">
                        <span class="mr-1 font-bold text-gray-500 dark:text-white">
                            {{ price($order->price) }}
                        </span>
                                / {{ $order->cycle() }}
                            </div>
                        </td>
                        <td class="hidden px-4 py-3 md:table-cell">
                            <div class="relative mt-0.5 inline-flex h-9 w-9 items-center justify-center overflow-hidden rounded-full border border-gray-500 bg-gray-100 dark:bg-gray-600">
                                <span class="font-medium text-gray-600 dark:text-gray-300">Mu</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($order->status == 'active')
                                <x-theme::badge.success text="Active" />
                            @elseif($order->status == 'suspended')
                                <x-theme::badge.warning text="Suspended" />
                            @elseif($order->status == 'cancelled')
                                <x-theme::badge.danger text="Cancelled" />
                            @elseif($order->status == 'terminated')
                                <x-theme::badge.danger text="Terminated" />
                            @elseif(in_array($order->status, ['pending', 'processing']))
                                <x-theme::badge.primary text="{{ ucfirst($order->status) }}" />
                            @else
                                <x-theme::badge.warning text="{{ ucfirst($order->status) }}" />
                            @endif
                        </td>
                        <td class="hidden px-4 py-3 sm:table-cell">
                            @if($order->due_date)
                                {{ $order->due_date->format('d M Y') }}
                            @else
                                Never
                            @endif
                        </td>
                        <td class="hidden px-4 py-3 lg:table-cell">
                            @if($order->due_date)
                                {{ $order->due_date }}
                            @else
                                Never
                            @endif
                        </td>
                    </tr>
                    <tr class="hidden flex-1 overflow-x-auto w-full" id="table-column-body-{{ $order->id }}" aria-labelledby="table-column-header-{{ $order->id }}">
                        <td class="p-4 border-b dark:border-gray-700" colspan="9">
                            <div class="mb-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                                <h6 class="mb-2 text-base font-medium leading-none text-gray-900 dark:text-white">
                                    Details
                                </h6>
                                <x-theme::datagrid.grid :cols="3" :gap="4">
                                    <x-theme::datagrid.item>
                                        <x-slot:label>Package</x-slot:label>
                                        {{ $order->package->name }}
                                    </x-theme::datagrid.item>

                                    <x-theme::datagrid.item>
                                        <x-slot:label>Billing cycle</x-slot:label>
                                        <span class="mr-1 font-bold text-gray-500 dark:text-white">{{ price($order->price) }}</span> / {{ $order->cycle() }}
                                    </x-theme::datagrid.item>

                                    <x-theme::datagrid.item>
                                        <x-slot:label>Status</x-slot:label>
                                        @if($order->status == 'active')
                                            <x-theme::badge.success text="Active" />
                                        @elseif($order->status == 'suspended')
                                            <x-theme::badge.warning text="Suspended" />
                                        @elseif($order->status == 'cancelled')
                                            <x-theme::badge.danger text="Cancelled" />
                                        @elseif($order->status == 'terminated')
                                            <x-theme::badge.danger text="Terminated" />
                                        @elseif(in_array($order->status, ['pending', 'processing']))
                                            <x-theme::badge.primary text="{{ ucfirst($order->status) }}" />
                                        @else
                                            <x-theme::badge.warning text="{{ ucfirst($order->status) }}" />
                                        @endif
                                    </x-theme::datagrid.item>

                                    <x-theme::datagrid.item>
                                        <x-slot:label>Due date</x-slot:label>
                                        @if($order->due_date)
                                            {{ $order->due_date->format('d M Y') }}
                                        @else
                                            Never
                                        @endif
                                    </x-theme::datagrid.item>

                                    <x-theme::datagrid.item>
                                        <x-slot:label>Last renewal date</x-slot:label>
                                        {{ $order->last_renewed_at->format('d M Y') }}
                                    </x-theme::datagrid.item>

                                    <x-theme::datagrid.item>
                                        <x-slot:label>Next Invoice</x-slot:label>
                                        @if($order->due_date)
                                            {{ $order->due_date->diffForHumans() }}
                                        @else
                                            Never
                                        @endif
                                    </x-theme::datagrid.item>
                                </x-theme::datagrid.grid>
                                <div class="mt-4 flex items-center space-x-3">
                                    <a href="{{ route('orders.view', $order->id) }}" wire:navigate class="bg-primary-700 hover:bg-primary-800 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800 flex items-center rounded-lg px-3 py-2 text-center text-sm font-medium text-white focus:outline-none focus:ring-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="mr-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"></path>
                                            <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"></path>
                                        </svg>
                                        Manage
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                    @endforeach
                </table>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-3 md:space-y-0 px-4 pt-3 pb-4" aria-label="Table navigation">

            </div>
        </div>
    </div>
    @endif
</section>
