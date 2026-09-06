<?php

use Extensions\Modules\Knowledgebase\Models\KnowledgebaseArticle;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    public function search(): void
    {
        $this->resetPage();
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }
}

?>

@php
    $term = trim($this->q);
    $articles = KnowledgebaseArticle::query()
        ->with('category')
        ->visibleTo(auth()->user())
        ->when($term !== '', fn ($query) => $query->search($term))
        ->popular()
        ->paginate(12);
@endphp

<section>
    <div class="mb-8 max-w-2xl">
        <nav class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            <a href="{{ route('knowledgebase.index') }}" wire:navigate class="hover:text-gray-900 dark:hover:text-white">{{ __('knowledgebase::messages.knowledgebase') }}</a>
            <span class="px-1.5">/</span>
            <span class="text-gray-900 dark:text-white">{{ __('knowledgebase::messages.search') }}</span>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('knowledgebase::messages.search') }}</h1>
        <form wire:submit="search" class="mt-4">
            <input
                type="search"
                wire:model.live.debounce.300ms="q"
                placeholder="{{ __('knowledgebase::messages.search_placeholder') }}"
                class="block w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            >
        </form>
    </div>

    @if($term === '')
        <p class="text-sm text-gray-500 dark:text-gray-400">Type a few words to search articles.</p>
    @elseif($articles->isEmpty())
        <x-theme::empty-state
            title="{{ __('knowledgebase::messages.no_results') }}"
            description="Try a shorter phrase, or browse categories from the knowledgebase home."
            action-text="{{ __('knowledgebase::messages.browse_docs') }}"
            :action-href="route('knowledgebase.index')"
            :action-navigate="true"
        />
    @else
        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ $articles->total() }} {{ \Illuminate\Support\Str::plural('result', $articles->total()) }}</p>
        <ul class="divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 bg-white dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
            @foreach($articles as $article)
                <li wire:key="result-{{ $article->id }}">
                    <a href="{{ $article->clientUrl() }}" wire:navigate class="block px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-900/40">
                        <div class="text-xs text-gray-400">{{ $article->category?->name }}</div>
                        <h2 class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $article->title }}</h2>
                        <p class="mt-1 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">{{ $article->displayExcerpt() }}</p>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">
            {{ $articles->links() }}
        </div>
    @endif
</section>
