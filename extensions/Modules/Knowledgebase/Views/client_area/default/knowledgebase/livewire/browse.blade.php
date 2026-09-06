<?php

use Extensions\Modules\Knowledgebase\Models\KnowledgebaseArticle;
use Extensions\Modules\Knowledgebase\Models\KnowledgebaseCategory;
use Livewire\Volt\Component;

new class extends Component
{
    public string $q = '';

    public function search(): mixed
    {
        $term = trim($this->q);

        if ($term === '') {
            return null;
        }

        return $this->redirect(route('knowledgebase.search', ['q' => $term]), navigate: true);
    }
}

?>

@php
    $user = auth()->user();
    $categories = KnowledgebaseCategory::query()
        ->with(['children' => fn ($query) => $query->visibleTo($user)->ordered()->withCount(['articles' => fn ($articles) => $articles->visibleTo($user)])])
        ->withCount(['articles' => fn ($query) => $query->visibleTo($user)])
        ->visibleTo($user)
        ->roots()
        ->ordered()
        ->get();

    $featured = KnowledgebaseArticle::query()
        ->with('category')
        ->visibleTo($user)
        ->featured()
        ->popular()
        ->limit(3)
        ->get();

    $popular = KnowledgebaseArticle::query()
        ->with('category')
        ->visibleTo($user)
        ->popular()
        ->limit(5)
        ->get();

    $recent = KnowledgebaseArticle::query()
        ->with('category')
        ->visibleTo($user)
        ->recentlyUpdated()
        ->limit(5)
        ->get();
@endphp

<section>
    <div class="mx-auto mb-10 max-w-2xl text-center">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ __('knowledgebase::messages.knowledgebase') }}</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Find answers about billing, services, and common technical questions.</p>
        <form wire:submit="search" class="mt-6">
            <label for="kb-search" class="sr-only">{{ __('knowledgebase::messages.search') }}</label>
            <div class="relative">
                <input
                    id="kb-search"
                    type="search"
                    wire:model="q"
                    placeholder="{{ __('knowledgebase::messages.search_placeholder') }}"
                    class="block w-full rounded-xl border border-gray-200 bg-white py-3.5 pl-4 pr-24 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                >
                <button type="submit" class="absolute inset-y-1.5 right-1.5 rounded-lg bg-primary-700 px-4 text-sm font-medium text-white hover:bg-primary-800 dark:bg-primary-600 dark:hover:bg-primary-700">
                    {{ __('knowledgebase::messages.search') }}
                </button>
            </div>
        </form>
    </div>

    @if($featured->isNotEmpty())
        <div class="mb-10 grid gap-4 md:grid-cols-3">
            @foreach($featured as $article)
                <a href="{{ $article->clientUrl() }}" wire:navigate wire:key="featured-{{ $article->id }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-600">
                    <span class="text-xs font-medium uppercase tracking-wide text-primary-700 dark:text-primary-400">{{ __('knowledgebase::messages.featured') }}</span>
                    <h2 class="mt-2 text-base font-semibold text-gray-900 dark:text-white">{{ $article->title }}</h2>
                    <p class="mt-2 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">{{ $article->displayExcerpt() }}</p>
                </a>
            @endforeach
        </div>
    @endif

    @if($categories->isEmpty())
        <x-theme::empty-state
            title="{{ __('knowledgebase::messages.no_categories') }}"
            description="Documentation will appear here once articles are published."
        />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($categories as $category)
                <a href="{{ $category->clientUrl() }}" wire:navigate wire:key="category-{{ $category->id }}" class="flex gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-600">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">
                        <x-knowledgebase::category-icon :icon="$category->icon" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="font-semibold text-gray-900 dark:text-white">{{ $category->name }}</h2>
                        @if($category->description)
                            <p class="mt-1 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">{{ $category->description }}</p>
                        @endif
                        <p class="mt-2 text-xs text-gray-400">
                            {{ $category->articles_count + $category->children->sum('articles_count') }}
                            {{ \Illuminate\Support\Str::plural('article', $category->articles_count + $category->children->sum('articles_count')) }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @if($popular->isNotEmpty() || $recent->isNotEmpty())
        <div class="mt-10 grid gap-8 lg:grid-cols-2">
            <div>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('knowledgebase::messages.popular') }}</h2>
                <ul class="divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 bg-white dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                    @foreach($popular as $article)
                        <li wire:key="popular-{{ $article->id }}">
                            <a href="{{ $article->clientUrl() }}" wire:navigate class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                <span class="min-w-0 truncate text-sm font-medium text-gray-900 dark:text-white">{{ $article->title }}</span>
                                <span class="shrink-0 text-xs text-gray-400">{{ number_format($article->views_count) }} {{ __('knowledgebase::messages.views') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('knowledgebase::messages.recently_updated') }}</h2>
                <ul class="divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 bg-white dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                    @foreach($recent as $article)
                        <li wire:key="recent-{{ $article->id }}">
                            <a href="{{ $article->clientUrl() }}" wire:navigate class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                <span class="min-w-0 truncate text-sm font-medium text-gray-900 dark:text-white">{{ $article->title }}</span>
                                <span class="shrink-0 text-xs text-gray-400">{{ $article->updated_at?->diffForHumans() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</section>
