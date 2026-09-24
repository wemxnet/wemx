<?php

use Extensions\Modules\Marketplace\Enums\LicenseStatus;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public int $licenseId;

    public function mount(): void
    {
        abort_unless($this->license->belongsToUser(auth()->user()), 404);
    }

    #[Computed]
    public function license(): MarketplaceLicense
    {
        return MarketplaceLicense::query()
            ->with(['resource.category', 'resource.author', 'resource.versions', 'sale', 'granter'])
            ->findOrFail($this->licenseId);
    }
}

?>

@php
    $license = $this->license;
    $resource = $license->resource;
    $latest = $resource?->latestApprovedVersion() ?? $resource?->latestVersion();
    $downloadableVersions = $resource
        ?->versions
        ->filter(fn ($version) => $version->isDownloadable($resource))
        ?? collect();
@endphp

<div>
    <a href="{{ route('marketplace.library.purchases') }}" wire:navigate class="mb-4 inline-flex items-center gap-1 text-sm text-primary-700 hover:underline dark:text-primary-300">
        ← Back to my purchases
    </a>

    <x-theme::card class="mb-4 !p-5">
        <div class="flex flex-wrap items-start gap-4">
            @if($resource)
                <x-marketplace::resource-icon :resource="$resource" size="lg" />
            @endif
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $resource?->name ?? 'Deleted resource' }}</h2>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $license->status === LicenseStatus::Active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">{{ $license->status->label() }}</span>
                </div>
                @if($resource?->category)
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $resource->category->name }}</p>
                @endif
                @if($resource?->short_description)
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $resource->short_description }}</p>
                @endif
                @if($resource?->author)
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        by <a href="{{ route('marketplace.authors.show', $resource->author->username) }}" wire:navigate class="font-medium text-primary-700 hover:underline dark:text-primary-300">{{ $resource->author->username }}</a>
                    </p>
                @endif
            </div>
            @if($resource)
                <a href="{{ $resource->clientUrl() }}" wire:navigate class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Open resource</a>
            @endif
        </div>
    </x-theme::card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-theme::card class="!p-5">
            <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Purchase details</h3>
            <dl class="space-y-4 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Purchased</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $license->purchasedAt()?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Access type</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $license->sourceLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Payment method</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $license->paymentMethodLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Transaction reference</dt>
                    <dd class="mt-1 break-all font-mono text-xs text-gray-800 dark:text-gray-200">{{ $license->transactionReference() ?: '—' }}</dd>
                </div>
                @if($license->sale)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Amount paid</dt>
                        <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $license->sale->formattedAmount() }}</dd>
                    </div>
                @endif
                @if($license->expires_at)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Expires</dt>
                        <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $license->expires_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd>
                    </div>
                @endif
                @if($license->granter)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Granted by</dt>
                        <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $license->granter->username }}</dd>
                    </div>
                @endif
            </dl>
        </x-theme::card>

        <x-theme::card class="!p-5">
            <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">License</h3>
            <dl class="space-y-4 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $license->status->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">License key</dt>
                    <dd class="mt-1 break-all rounded-lg bg-gray-50 p-3 font-mono text-xs text-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ $license->license_key }}</dd>
                </div>
                @if($license->domain)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Domain</dt>
                        <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $license->domain }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Activations</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $license->activations_count }} / {{ $license->max_activations }}</dd>
                </div>
            </dl>
        </x-theme::card>
    </div>

    @if($license->status === LicenseStatus::Active && $resource && $downloadableVersions->isNotEmpty())
        <x-theme::card class="mt-4 !p-5">
            <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Downloads</h3>
            <div class="space-y-2">
                @foreach($downloadableVersions as $version)
                    <div wire:key="purchase-detail-version-{{ $version->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">{{ $version->name }} <span class="font-mono text-sm text-gray-500 dark:text-gray-400">v{{ $version->version }}</span></div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $version->created_at?->format('M j, Y') }} · {{ $version->humanSize() }}</p>
                        </div>
                        <x-theme::button.primary href="{{ route('marketplace.versions.download', $version) }}" class="!px-4 !py-2 text-sm">
                            Download
                            @if($latest && $version->id === $latest->id)
                                <span class="sr-only">(latest)</span>
                            @endif
                        </x-theme::button.primary>
                    </div>
                @endforeach
            </div>
        </x-theme::card>
    @elseif($license->status !== LicenseStatus::Active)
        <x-theme::alert.warning class="mt-4" text="This license is not active, so downloads are unavailable." />
    @endif
</div>
