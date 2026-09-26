<?php

use App\Services\IntegratedMarketplace;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component
{
    #[Url]
    public string $q = '';

    #[Url]
    public ?string $category = null;

    #[Url]
    public string $sort = 'popular';

    #[Url]
    public int $page = 1;

    public function updatingQ(): void
    {
        $this->page = 1;
    }

    public function updatingCategory(): void
    {
        $this->page = 1;
    }

    public function updatingSort(): void
    {
        $this->page = 1;
    }

    public function mount(): void
    {
        $this->forgetDisallowedCategory();
    }

    public function setCategory(?string $slug): void
    {
        $this->category = $slug;
        $this->forgetDisallowedCategory();
        $this->page = 1;
    }

    private function forgetDisallowedCategory(): void
    {
        if ($this->category !== null && ! in_array($this->category, IntegratedMarketplace::CATEGORY_SLUGS, true)) {
            $this->category = null;
        }
    }

    public function setPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function catalog(): array
    {
        $sorts = collect($this->sortOptions())->pluck('value')->all();

        return app(IntegratedMarketplace::class)->catalog([
            'search' => $this->q,
            'category' => $this->category,
            'sort_by' => in_array($this->sort, $sorts, true) ? $this->sort : 'popular',
            'page' => $this->page,
            'per_page' => IntegratedMarketplace::PER_PAGE,
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function sortOptions(): array
    {
        return [
            ['value' => 'popular', 'label' => 'Popular (All)'],
            ['value' => 'popular_free', 'label' => 'Popular (Free)'],
            ['value' => 'updated', 'label' => 'Last Updated'],
            ['value' => 'downloads', 'label' => 'Most Downloads'],
            ['value' => 'purchases', 'label' => 'Most Purchases'],
        ];
    }
}

?>

@php
    $catalog = $this->catalog;
    $resources = collect($catalog['resources']);
    $featured = collect($catalog['featured']);
    $featuredSlugs = $featured->pluck('slug');
    $showFeatured = $this->sort === 'popular' && $this->q === '' && $this->category === null && $featured->isNotEmpty();

    if ($showFeatured) {
        $resources = $resources->reject(fn (array $resource) => $featuredSlugs->contains($resource['slug'] ?? null))->values();
    }
@endphp

<div>
    <p class="text-secondary mb-3">Browse servers, modules, and payment gateways published on the marketplace.</p>

    @if($catalog['error'])
        <div class="alert alert-warning" role="alert">{{ $catalog['error'] }}</div>
    @endif

    <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 mb-3">
        <div class="d-flex flex-wrap gap-2">
            <button type="button" wire:click="setCategory(null)" class="btn btn-sm {{ $category === null ? 'btn-primary' : 'btn-outline-secondary' }}">All</button>
            @foreach($catalog['categories'] as $item)
                <button type="button" wire:key="category-{{ $item['slug'] }}" wire:click="setCategory(@js($item['slug']))" class="btn btn-sm {{ $category === $item['slug'] ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $item['name'] }}
                </button>
            @endforeach
        </div>
        <div class="d-flex flex-column flex-sm-row gap-2 ms-lg-auto">
            <select wire:model.live="sort" class="form-select" style="min-width: 12rem;">
                @foreach($this->sortOptions() as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
            <input type="search" wire:model.live.debounce.300ms="q" class="form-control" placeholder="Search resources…" style="min-width: 16rem;">
        </div>
    </div>

    @if($showFeatured)
        <h3 class="subheader mb-2">Featured</h3>
        <div class="row row-cards mb-4">
            @foreach($featured as $resource)
                @include('admin::integrated-marketplace.partials.resource-card', ['resource' => $resource])
            @endforeach
        </div>
    @endif

    @if($resources->isEmpty() && ! $showFeatured)
        <div class="empty">
            <p class="empty-title">No resources found</p>
            <p class="empty-subtitle text-secondary">Try another search or category. Approved listings appear here after they are published to the marketplace.</p>
        </div>
    @elseif($resources->isNotEmpty())
        <div class="row row-cards">
            @foreach($resources as $resource)
                @include('admin::integrated-marketplace.partials.resource-card', ['resource' => $resource])
            @endforeach
        </div>
    @endif

    @if($catalog['last_page'] > 1)
        <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="text-secondary">Page {{ $catalog['page'] }} of {{ $catalog['last_page'] }}</span>
            <div class="btn-list">
                <button type="button" class="btn" wire:click="setPage({{ max(1, $catalog['page'] - 1) }})" @disabled($catalog['page'] <= 1)>Previous</button>
                <button type="button" class="btn" wire:click="setPage({{ min($catalog['last_page'], $catalog['page'] + 1) }})" @disabled($catalog['page'] >= $catalog['last_page'])>Next</button>
            </div>
        </div>
    @endif
</div>
