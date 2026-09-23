<?php

use Extensions\Modules\Marketplace\Models\MarketplaceCategory;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public ?string $category = null;

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function setCategory(?string $slug): void
    {
        $this->category = $slug;
        $this->resetPage();
    }
}

?>

@php
    $user = auth()->user();
    $categories = MarketplaceCategory::query()->visible()->ordered()->get();

    $featured = MarketplaceResource::query()
        ->with(['category', 'author', 'versions'])
        ->visibleTo($user)
        ->featured()
        ->popular()
        ->limit(3)
        ->get();

    $query = MarketplaceResource::query()
        ->with(['category', 'author', 'versions'])
        ->visibleTo($user)
        ->popular();

    if ($featured->isNotEmpty() && $this->q === '' && $this->category === null) {
        $query->whereNotIn('id', $featured->modelKeys());
    }

    if ($this->category) {
        $query->whereHas('category', fn ($category) => $category->where('slug', $this->category));
    }

    if ($this->q !== '') {
        $query->search($this->q);
    }

    $resources = $query->paginate(12);
@endphp

<section>
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ __('marketplace::messages.marketplace') }}</h1>
            <p class="mt-2 max-w-2xl text-sm text-gray-500 dark:text-gray-400">Browse WemX servers, modules, gateways, and themes. Featured listings stay at the top; everything else is ranked by views and downloads.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @auth
                <a href="{{ route('marketplace.studio.index') }}" wire:navigate class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Creator studio</a>
                <x-theme::button.primary href="{{ route('marketplace.studio.create') }}" wire:navigate>Publish a resource</x-theme::button.primary>
            @else
                <x-theme::button.primary href="{{ route('login') }}">Sign in to publish</x-theme::button.primary>
            @endauth
        </div>
    </div>

    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="setCategory(null)" class="rounded-full px-3 py-1.5 text-sm {{ $category === null ? 'bg-primary-700 text-white dark:bg-primary-600' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">All</button>
            @foreach($categories as $item)
                <button type="button" wire:click="setCategory(@js($item->slug))" class="rounded-full px-3 py-1.5 text-sm {{ $category === $item->slug ? 'bg-primary-700 text-white dark:bg-primary-600' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                    {{ $item->name }}
                </button>
            @endforeach
        </div>
        <div class="lg:ml-auto lg:w-80">
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="Search resources…" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
        </div>
    </div>

    @if($featured->isNotEmpty() && $this->q === '' && $this->category === null)
        <div class="mb-8">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('marketplace::messages.featured') }}</h2>
            <div class="grid gap-4 md:grid-cols-3">
                @foreach($featured as $resource)
                    @include('marketplace::client_area.default.marketplace.partials.resource-card', ['resource' => $resource])
                @endforeach
            </div>
        </div>
    @endif

    @if($resources->isEmpty() && ($featured->isEmpty() || $this->q !== '' || $this->category !== null))
        <x-theme::empty-state
            title="{{ __('marketplace::messages.no_resources') }}"
            description="Resources appear here after an administrator approves them."
        />
    @elseif($resources->isNotEmpty())
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($resources as $resource)
                @include('marketplace::client_area.default.marketplace.partials.resource-card', ['resource' => $resource])
            @endforeach
        </div>
        <div class="mt-8">
            {{ $resources->links() }}
        </div>
    @endif
</section>
