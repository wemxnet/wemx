<?php

use App\Models\User;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $username;

    public string $tab = 'authored';

    public function mount(): void
    {
        abort_unless($this->author, 404);
    }

    public function updatingTab(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['authored', 'collaborations'], true) ? $tab : 'authored';
        $this->resetPage();
    }

    #[Computed]
    public function author(): User
    {
        return User::query()->where('username', $this->username)->firstOrFail();
    }
}

?>

@php
    $viewer = auth()->user();
    $author = $this->author;

    $authoredQuery = MarketplaceResource::query()
        ->with(['category', 'author', 'versions'])
        ->visibleTo($viewer)
        ->authoredBy($author)
        ->popular();

    $collaboratedQuery = MarketplaceResource::query()
        ->with(['category', 'author', 'versions'])
        ->visibleTo($viewer)
        ->collaboratedBy($author)
        ->popular();

    $authoredCount = (clone $authoredQuery)->count();
    $collaboratedCount = (clone $collaboratedQuery)->count();

    $resources = ($this->tab === 'collaborations' ? $collaboratedQuery : $authoredQuery)->paginate(12);
@endphp

<section>
    <nav class="mb-6 text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('marketplace.index') }}" wire:navigate class="hover:text-gray-900 dark:hover:text-white">Marketplace</a>
        <span class="px-1.5">/</span>
        <span class="text-gray-900 dark:text-white">{{ $author->username }}</span>
    </nav>

    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center">
        <img src="{{ $author->getAvatarUrl() }}" alt="" class="h-16 w-16 rounded-full border border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $author->username }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ number_format($authoredCount) }} {{ Str::plural('resource', $authoredCount) }}
                · {{ number_format($collaboratedCount) }} {{ Str::plural('collaboration', $collaboratedCount) }}
            </p>
        </div>
    </div>

    <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex flex-wrap gap-6">
            <button
                type="button"
                wire:click="setTab('authored')"
                class="border-b-2 pb-3 text-sm font-medium transition {{ $this->tab === 'authored' ? 'border-primary-600 text-primary-700 dark:border-primary-400 dark:text-primary-300' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >Authored ({{ $authoredCount }})</button>
            <button
                type="button"
                wire:click="setTab('collaborations')"
                class="border-b-2 pb-3 text-sm font-medium transition {{ $this->tab === 'collaborations' ? 'border-primary-600 text-primary-700 dark:border-primary-400 dark:text-primary-300' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >Collaborations ({{ $collaboratedCount }})</button>
        </nav>
    </div>

    @if($resources->isEmpty())
        <x-theme::empty-state
            title="{{ $this->tab === 'collaborations' ? 'No collaborations yet' : 'No resources yet' }}"
            description="{{ $this->tab === 'collaborations' ? 'This user has not collaborated on any public resources.' : 'This user has not published any public resources yet.' }}"
        />
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($resources as $resource)
                @include('marketplace::client_area.default.marketplace.partials.resource-card', ['resource' => $resource])
            @endforeach
        </div>
        <div class="mt-8">{{ $resources->links() }}</div>
    @endif
</section>
