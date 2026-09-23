<?php

use Extensions\Modules\Marketplace\Enums\LicenseStatus;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    public function updatingQ(): void
    {
        $this->resetPage();
    }

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
        ->whereHas('resource', function ($query) {
            $query->where('user_id', auth()->id())
                ->orWhereHas('teamMembers', fn ($team) => $team->where('user_id', auth()->id()));
        })
        ->search($this->q)
        ->orderByDesc('purchased_at')
        ->orderByDesc('created_at')
        ->paginate(20);
@endphp

<div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Purchases</h1>
        <div class="sm:w-80">
            <x-theme::form.input type="search" wire:model.live.debounce.300ms="q" placeholder="Search customer, resource, key…"/>
        </div>
    </div>

    @if($licenses->isEmpty())
        <x-theme::empty-state
            title="{{ $this->q !== '' ? 'No matching purchases' : __('marketplace::messages.no_licenses') }}"
            :description="$this->q !== '' ? 'Try a different search term.' : 'Purchases appear when someone buys a resource, downloads a free resource, or you grant access.'"
        />
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Resource</th>
                        <th class="px-4 py-3">Purchased</th>
                        <th class="px-4 py-3">Payment</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($licenses as $license)
                        <tr wire:key="license-{{ $license->id }}" class="text-gray-700 dark:text-gray-200">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $license->user?->username }}</div>
                                <div class="font-mono text-[11px] text-gray-500 dark:text-gray-400">{{ $license->license_key }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $license->resource?->name }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $license->purchasedAt()?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div>{{ $license->paymentMethodLabel() }}</div>
                                @if($license->transactionReference())
                                    <div class="font-mono text-[11px] text-gray-500 dark:text-gray-400">{{ $license->transactionReference() }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $license->status->label() }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($license->status === LicenseStatus::Active)
                                    <button type="button" class="text-red-600 hover:underline dark:text-red-400" wire:click="revoke({{ $license->id }})" wire:confirm="Revoke this customer's access?">Revoke</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $licenses->links() }}</div>
    @endif
</div>
