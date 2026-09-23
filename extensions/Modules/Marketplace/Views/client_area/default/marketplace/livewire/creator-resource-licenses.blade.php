<?php

use Extensions\Modules\Marketplace\Enums\LicenseStatus;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public int $resourceId;

    #[Url]
    public string $q = '';

    public string $username = '';

    public string $payment_method = '';

    public string $transaction_id = '';

    public bool $notify = true;

    public function mount(): void
    {
        abort_unless($this->resource->userCan(auth()->user(), TeamRole::Support), 403);
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function resource(): MarketplaceResource
    {
        return MarketplaceResource::query()->findOrFail($this->resourceId);
    }

    public function grant(): void
    {
        MarketplaceLicense::actions()->grantAsManager([
            'actor_user_id' => auth()->id(),
            'resource_id' => $this->resourceId,
            'username' => $this->username,
            'payment_method' => $this->payment_method ?: null,
            'transaction_id' => $this->transaction_id ?: null,
            'notify' => $this->notify,
        ]);

        $this->reset(['username', 'payment_method', 'transaction_id']);
        $this->notify = true;
        $this->resetPage();
        session()->flash('success', 'Purchase access granted.');
    }

    public function revoke(int $licenseId): void
    {
        MarketplaceLicense::actions()->revoke([
            'actor_user_id' => auth()->id(),
            'license_id' => $licenseId,
        ]);

        session()->flash('success', 'Access revoked.');
    }
}

?>

@php
    $licenses = MarketplaceLicense::query()
        ->with(['user', 'sale', 'granter'])
        ->where('resource_id', $resourceId)
        ->search($this->q)
        ->orderByDesc('purchased_at')
        ->orderByDesc('created_at')
        ->paginate(20);
@endphp

<div>
    @if(session('success'))
        <x-theme::alert.success :text="session('success')" />
    @endif

    <x-theme::card class="mb-4">
        <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Grant access</h3>
        <form wire:submit="grant" class="space-y-3">
            <div>
                <x-theme::form.label for="username" text="Username or email"/>
                <x-theme::form.input id="username" wire:model="username" placeholder="customer@example.com"/>
                @error('username') <x-theme::form.error :text="$message"/> @enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <x-theme::form.label for="payment_method" text="Payment method (optional)"/>
                    <x-theme::form.input id="payment_method" wire:model="payment_method" placeholder="Stripe, PayPal, Manual…"/>
                </div>
                <div>
                    <x-theme::form.label for="transaction_id" text="Transaction ID (optional)"/>
                    <x-theme::form.input id="transaction_id" wire:model="transaction_id" placeholder="txn_…"/>
                </div>
            </div>
            <x-theme::form.toggle wire:model="notify" text="Email the customer about their access"/>
            <x-theme::button.primary type="submit">Add purchaser</x-theme::button.primary>
        </form>
    </x-theme::card>

    <x-theme::card>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Purchases</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Customers who purchased this resource or were granted access.</p>
            </div>
            <div class="sm:w-72">
                <x-theme::form.input type="search" wire:model.live.debounce.300ms="q" placeholder="Search customer, key, payment…"/>
            </div>
        </div>

        @if($licenses->isEmpty())
            <x-theme::empty-state
                title="{{ $this->q !== '' ? 'No matching purchases' : __('marketplace::messages.no_licenses') }}"
                :description="$this->q !== '' ? 'Try a different search term.' : 'Purchases appear here when someone buys the resource, downloads a free resource, or you grant access manually.'"
            />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Purchased</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3">Transaction</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($licenses as $license)
                            <tr wire:key="license-{{ $license->id }}" class="text-gray-700 dark:text-gray-200">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $license->user?->username }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $license->user?->email }}</div>
                                    <div class="mt-1 font-mono text-[11px] text-gray-500 dark:text-gray-400">{{ $license->license_key }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $license->purchasedAt()?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-3">{{ $license->paymentMethodLabel() }}</td>
                                <td class="px-4 py-3">
                                    <span class="break-all font-mono text-xs">{{ $license->transactionReference() ?: '—' }}</span>
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
    </x-theme::card>
</div>
