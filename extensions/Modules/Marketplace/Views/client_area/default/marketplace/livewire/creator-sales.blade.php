<?php

use Extensions\Modules\Marketplace\Models\MarketplaceSale;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
}

?>

@php
    $sales = MarketplaceSale::query()
        ->with(['resource', 'buyer'])
        ->where('seller_id', auth()->id())
        ->latest()
        ->paginate(20);
@endphp

<div>
    <h1 class="mb-4 text-2xl font-bold text-gray-900 dark:text-white">Sales</h1>
    @if($sales->isEmpty())
        <x-theme::empty-state title="{{ __('marketplace::messages.no_sales') }}" description="Completed purchases of your resources will show up here." />
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3">Resource</th>
                        <th class="px-4 py-3">Buyer</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($sales as $sale)
                        <tr wire:key="sale-{{ $sale->id }}">
                            <td class="px-4 py-3">{{ $sale->resource?->name }}</td>
                            <td class="px-4 py-3">{{ $sale->buyer?->username }}</td>
                            <td class="px-4 py-3">{{ $sale->formattedAmount() }}</td>
                            <td class="px-4 py-3">{{ $sale->status->label() }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $sale->created_at?->toDayDateTimeString() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $sales->links() }}</div>
    @endif
</div>
