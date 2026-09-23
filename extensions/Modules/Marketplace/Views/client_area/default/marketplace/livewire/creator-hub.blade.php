<?php

use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Models\MarketplaceSale;
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
    $userId = auth()->id();

    $resourceBase = MarketplaceResource::query()
        ->where(function ($query) use ($userId) {
            $query->where('user_id', $userId)
                ->orWhereHas('teamMembers', fn ($team) => $team->where('user_id', $userId));
        });

    $resourceTotal = (clone $resourceBase)->count();

    $resources = (clone $resourceBase)
        ->with('category')
        ->search($this->q)
        ->latest()
        ->paginate(12);

    $salesCount = MarketplaceSale::query()->where('seller_id', $userId)->completed()->count();
    $licenseCount = MarketplaceLicense::query()->whereHas('resource', fn ($query) => $query->where('user_id', $userId))->count();
@endphp

<div>
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="text-xs uppercase tracking-wide text-gray-500">Resources</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $resourceTotal }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="text-xs uppercase tracking-wide text-gray-500">Completed sales</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $salesCount }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Purchases</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $licenseCount }}</div>
        </div>
    </div>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Your resources</h2>
        <div class="sm:w-80">
            <x-theme::form.input type="search" wire:model.live.debounce.300ms="q" placeholder="Search resources…"/>
        </div>
    </div>

    @if($resources->isEmpty())
        <x-theme::empty-state
            title="{{ $this->q !== '' ? 'No matching resources' : 'No resources yet' }}"
            :description="$this->q !== '' ? 'Try a different search term.' : 'Publish your first integration, theme, or gateway.'"
        />
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Resource</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Price</th>
                        <th class="px-4 py-3">Stats</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($resources as $resource)
                        <tr wire:key="studio-{{ $resource->id }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <x-marketplace::resource-icon :resource="$resource" size="sm" />
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $resource->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $resource->category?->name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $resource->status->label() }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $resource->formattedPrice() }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $resource->views_count }} views · {{ $resource->downloads_count }} downloads</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ $resource->studioUrl() }}" wire:navigate class="text-primary-700 hover:underline dark:text-primary-300">Manage</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $resources->links() }}</div>
    @endif
</div>
