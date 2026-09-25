<?php

use Extensions\Modules\Marketplace\Enums\LicenseStatus;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Illuminate\Support\Str;
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
}

?>

@php
    $licenses = MarketplaceLicense::query()
        ->with(['resource.category', 'resource.author', 'resource.versions', 'sale'])
        ->where('user_id', auth()->id())
        ->search($this->q)
        ->orderByDesc('purchased_at')
        ->orderByDesc('created_at')
        ->paginate(12);
@endphp

<div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $licenses->total() }} {{ Str::plural('purchase', $licenses->total()) }}
        </p>
        <div class="sm:w-80">
            <x-theme::form.input type="search" wire:model.live.debounce.300ms="q" placeholder="Search resource, license key, transaction…"/>
        </div>
    </div>

    @if($licenses->isEmpty())
        <x-theme::card>
            <x-theme::empty-state
                title="{{ $this->q !== '' ? 'No matching purchases' : 'No purchases yet' }}"
                :description="$this->q !== '' ? 'Try a different search term.' : 'When you buy a marketplace resource, it will show up here with your license and download access.'"
            />
            @if($this->q === '')
                <div class="mt-4">
                    <x-theme::button.primary href="{{ route('marketplace.index') }}" wire:navigate>Browse marketplace</x-theme::button.primary>
                </div>
            @endif
        </x-theme::card>
    @else
        <div class="space-y-3">
            @foreach($licenses as $license)
                @php
                    $resource = $license->resource;
                    $latest = $resource?->latestApprovedVersion() ?? $resource?->latestVersion();
                    $downloadableVersions = $resource
                        ?->versions
                        ->filter(fn ($version) => $version->downloadableFromExtensionMarketplace($resource))
                        ?? collect();
                @endphp
                <x-theme::card wire:key="purchase-{{ $license->id }}" class="!p-4">
                    <div class="flex flex-wrap items-start gap-4">
                        @if($resource)
                            <x-marketplace::resource-icon :resource="$resource" size="md" />
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $resource?->name ?? 'Deleted resource' }}</h2>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $license->status === LicenseStatus::Active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">{{ $license->status->label() }}</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $resource?->short_description }}</p>
                            <dl class="mt-3 grid gap-2 text-xs text-gray-500 dark:text-gray-400 sm:grid-cols-3">
                                <div>
                                    <dt class="uppercase tracking-wide">Purchased</dt>
                                    <dd class="mt-0.5 font-medium text-gray-800 dark:text-gray-200">{{ $license->purchasedAt()?->format('M j, Y') ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="uppercase tracking-wide">Payment</dt>
                                    <dd class="mt-0.5 font-medium text-gray-800 dark:text-gray-200">{{ $license->paymentMethodLabel() }}</dd>
                                </div>
                                <div>
                                    <dt class="uppercase tracking-wide">Access</dt>
                                    <dd class="mt-0.5 font-medium text-gray-800 dark:text-gray-200">{{ $license->sourceLabel() }}</dd>
                                </div>
                            </dl>
                        </div>
                        <div class="flex w-full flex-col gap-2 sm:w-auto">
                            <a href="{{ route('marketplace.library.purchases.show', $license) }}" wire:navigate class="block rounded-lg border border-gray-300 px-5 py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Purchase details</a>
                            @if($resource)
                                <a href="{{ $resource->clientUrl() }}" wire:navigate class="block rounded-lg border border-gray-300 px-5 py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">View resource</a>
                            @endif
                            @if($license->status === LicenseStatus::Active && $resource && $latest?->integrated_marketplace_only)
                                <x-theme::alert.warning class="!mb-0" text="This version can only be installed through the integrated marketplace." />
                            @endif
                            @if($license->status === LicenseStatus::Active && $resource && $downloadableVersions->isNotEmpty())
                                @if($downloadableVersions->count() === 1)
                                    <x-theme::button.primary href="{{ route('marketplace.versions.download', $downloadableVersions->first()) }}" class="text-center">Download</x-theme::button.primary>
                                @else
                                    <details class="group">
                                        <summary class="block cursor-pointer list-none rounded-lg bg-primary-600 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-400">
                                            Download version
                                        </summary>
                                        <div class="mt-2 space-y-1 rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-800">
                                            @foreach($downloadableVersions as $version)
                                                <a
                                                    wire:key="purchase-version-{{ $license->id }}-{{ $version->id }}"
                                                    href="{{ route('marketplace.versions.download', $version) }}"
                                                    class="block rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700"
                                                >
                                                    v{{ $version->version }}
                                                    @if($latest && $version->id === $latest->id)
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">(latest)</span>
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            @endif
                        </div>
                    </div>
                </x-theme::card>
            @endforeach
        </div>
        <div class="mt-4">{{ $licenses->links() }}</div>
    @endif
</div>
