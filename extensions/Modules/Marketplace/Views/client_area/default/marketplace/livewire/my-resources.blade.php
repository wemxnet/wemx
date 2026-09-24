<?php

use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Support\MarketplaceLimits;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
}

?>

@php
    $userId = auth()->id();
    $canCreateResource = true;
    $createBlockedMessage = null;

    try {
        MarketplaceLimits::assertCanCreateResource(auth()->user());
    } catch (ValidationException $exception) {
        $canCreateResource = false;
        $createBlockedMessage = collect($exception->errors())->flatten()->first();
    }

    $resources = MarketplaceResource::query()
        ->with('category')
        ->where(function ($query) use ($userId) {
            $query->where('user_id', $userId)
                ->orWhereHas('teamMembers', fn ($team) => $team->where('user_id', $userId));
        })
        ->latest()
        ->paginate(12);
@endphp

<div>
    @if(! $canCreateResource && $createBlockedMessage)
        <x-theme::alert.warning :text="$createBlockedMessage" class="mb-4" />
    @endif

    <div class="mb-4 flex justify-end">
        @if($canCreateResource)
            <x-theme::button.primary href="{{ route('marketplace.studio.create') }}" wire:navigate>New resource</x-theme::button.primary>
        @endif
    </div>

    @if($resources->isEmpty())
        <x-theme::card>
            <x-theme::empty-state title="No resources yet" description="Publish a module, theme, or gateway to the marketplace." />
        </x-theme::card>
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
                        <tr wire:key="my-resource-{{ $resource->id }}">
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
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                {{ $resource->views_count }} views · {{ $resource->downloads_count }} downloads
                                @if($resource->reviews_count)
                                    · {{ number_format((float) $resource->reviews_avg, 1) }}★
                                @endif
                            </td>
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
