<?php

use Extensions\Modules\Knowledgebase\Models\KnowledgebaseCategory;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public int $categoryId;

    #[Computed]
    public function category(): KnowledgebaseCategory
    {
        return KnowledgebaseCategory::query()
            ->with(['parent', 'children' => fn ($query) => $query->visibleTo(auth()->user())->ordered()->withCount(['articles' => fn ($articles) => $articles->visibleTo(auth()->user())])])
            ->findOrFail($this->categoryId);
    }
}

?>

@php
    $category = $this->category;
    $user = auth()->user();
    abort_unless($category->isVisibleTo($user), 404);
    $articles = $category->articles()->with('category')->visibleTo($user)->ordered()->get();
    $children = $category->children;
@endphp

<section>
    <nav class="mb-6 text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('knowledgebase.index') }}" wire:navigate class="hover:text-gray-900 dark:hover:text-white">{{ __('knowledgebase::messages.knowledgebase') }}</a>
        @foreach($category->breadcrumbs() as $crumb)
            <span class="px-1.5">/</span>
            @if($crumb->is($category))
                <span class="text-gray-900 dark:text-white">{{ $crumb->name }}</span>
            @else
                <a href="{{ $crumb->clientUrl() }}" wire:navigate class="hover:text-gray-900 dark:hover:text-white">{{ $crumb->name }}</a>
            @endif
        @endforeach
    </nav>

    <div class="mb-8 flex items-start gap-4">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">
            <x-knowledgebase::category-icon :icon="$category->icon" class="h-5 w-5" />
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $category->name }}</h1>
            @if($category->description)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $category->description }}</p>
            @endif
        </div>
    </div>

    @if($children->isNotEmpty())
        <div class="mb-8 grid gap-4 sm:grid-cols-2">
            @foreach($children as $child)
                <a href="{{ $child->clientUrl() }}" wire:navigate wire:key="child-{{ $child->id }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm hover:border-primary-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-600">
                    <h2 class="font-semibold text-gray-900 dark:text-white">{{ $child->name }}</h2>
                    <p class="mt-1 text-xs text-gray-400">{{ $child->articles_count }} {{ \Illuminate\Support\Str::plural('article', $child->articles_count) }}</p>
                </a>
            @endforeach
        </div>
    @endif

    @if($articles->isEmpty() && $children->isEmpty())
        <x-theme::empty-state
            title="{{ __('knowledgebase::messages.no_articles') }}"
            description="This category does not have any published articles yet."
        />
    @elseif($articles->isNotEmpty())
        <ul class="divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 bg-white dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
            @foreach($articles as $article)
                <li wire:key="article-{{ $article->id }}">
                    <a href="{{ $article->clientUrl() }}" wire:navigate class="block px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-900/40">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h2 class="font-semibold text-gray-900 dark:text-white">{{ $article->title }}</h2>
                                <p class="mt-1 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">{{ $article->displayExcerpt() }}</p>
                            </div>
                            <span class="shrink-0 text-xs text-gray-400">{{ number_format($article->views_count) }} {{ __('knowledgebase::messages.views') }}</span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
