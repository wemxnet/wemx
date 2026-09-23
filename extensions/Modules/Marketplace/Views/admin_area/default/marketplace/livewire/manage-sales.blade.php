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
    $sales = MarketplaceSale::query()->with(['resource', 'buyer', 'seller'])->latest()->paginate(25);
@endphp

<div>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Sales</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Resource</th>
                        <th>Buyer</th>
                        <th>Seller</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        <tr wire:key="admin-sale-{{ $sale->id }}">
                            <td>{{ $sale->resource?->name }}</td>
                            <td>{{ $sale->buyer?->username }}</td>
                            <td>{{ $sale->seller?->username }}</td>
                            <td>{{ $sale->formattedAmount() }}</td>
                            <td>{{ $sale->status->label() }}</td>
                            <td>{{ $sale->created_at?->toDayDateTimeString() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-secondary">No marketplace sales yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sales->hasPages())
            <div class="card-footer">{{ $sales->links() }}</div>
        @endif
    </div>
</div>
