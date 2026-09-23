<?php

use Extensions\Modules\Marketplace\Enums\LicenseStatus;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
}

?>

@php
    $licenses = MarketplaceLicense::query()
        ->with(['resource.category', 'resource.author', 'sale'])
        ->where('user_id', auth()->id())
        ->orderByDesc('purchased_at')
        ->orderByDesc('created_at')
        ->paginate(12);
@endphp

<div>
    @if($licenses->isEmpty())
        <x-theme::card>
            <x-theme::empty-state title="No purchases yet" description="When you buy a marketplace resource, it will show up here with download access." />
            <div class="mt-4">
                <x-theme::button.primary href="{{ route('marketplace.index') }}" wire:navigate>Browse marketplace</x-theme::button.primary>
            </div>
        </x-theme::card>
    @else
        <div class="space-y-3">
            @foreach($licenses as $license)
                @php
                    $resource = $license->resource;
                    $latest = $resource?->latestApprovedVersion() ?? $resource?->latestVersion();
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
                                    <dt class="uppercase tracking-wide">License</dt>
                                    <dd class="mt-0.5 break-all font-mono text-[11px] text-gray-800 dark:text-gray-200">{{ $license->license_key }}</dd>
                                </div>
                            </dl>
                        </div>
                        <div class="flex w-full flex-col gap-2 sm:w-auto">
                            @if($resource)
                                <a href="{{ $resource->clientUrl() }}" wire:navigate class="block rounded-lg border border-gray-300 px-5 py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">View</a>
                            @endif
                            @if($latest && $license->status === LicenseStatus::Active && $resource)
                                <x-theme::button.primary href="{{ route('marketplace.versions.download', $latest) }}" class="text-center">Download</x-theme::button.primary>
                            @endif
                        </div>
                    </div>
                </x-theme::card>
            @endforeach
        </div>
        <div class="mt-4">{{ $licenses->links() }}</div>
    @endif
</div>
