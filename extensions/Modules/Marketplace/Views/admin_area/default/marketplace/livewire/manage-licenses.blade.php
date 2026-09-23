<?php

use Extensions\Modules\Marketplace\Enums\LicenseStatus;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public function revoke(int $licenseId): void
    {
        MarketplaceLicense::actions()->revoke([
            'actor_user_id' => auth()->id(),
            'license_id' => $licenseId,
        ]);
    }
}

?>

@php
    $licenses = MarketplaceLicense::query()
        ->with(['resource', 'user', 'sale'])
        ->orderByDesc('purchased_at')
        ->orderByDesc('created_at')
        ->paginate(25);
@endphp

<div>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Purchases</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Resource</th>
                        <th>Purchased</th>
                        <th>Payment</th>
                        <th>Transaction</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($licenses as $license)
                        <tr wire:key="admin-lic-{{ $license->id }}">
                            <td>
                                <div>{{ $license->user?->username }}</div>
                                <div class="text-secondary small"><code>{{ $license->license_key }}</code></div>
                            </td>
                            <td>{{ $license->resource?->name }}</td>
                            <td>{{ $license->purchasedAt()?->format('Y-m-d') ?? '—' }}</td>
                            <td>{{ $license->paymentMethodLabel() }}</td>
                            <td><code>{{ $license->transactionReference() ?: '—' }}</code></td>
                            <td>{{ $license->status->label() }}</td>
                            <td class="text-end">
                                @if($license->status === LicenseStatus::Active)
                                    <button type="button" class="btn btn-link btn-sm text-danger" wire:click="revoke({{ $license->id }})" wire:confirm="Revoke this purchase access?">Revoke</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-secondary">No purchases yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($licenses->hasPages())
            <div class="card-footer">{{ $licenses->links() }}</div>
        @endif
    </div>
</div>
