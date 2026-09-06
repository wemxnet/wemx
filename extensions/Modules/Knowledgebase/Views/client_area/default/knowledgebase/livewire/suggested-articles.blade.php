<?php

use Extensions\Modules\Knowledgebase\Models\KnowledgebaseArticle;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;

new class extends Component
{
    #[Reactive]
    public string $query = '';
}

?>

@php
    $term = trim($this->query);
    $suggestions = $term === '' || strlen($term) < 3
        ? collect()
        : KnowledgebaseArticle::query()
            ->with('category')
            ->visibleTo(auth()->user())
            ->search($term)
            ->popular()
            ->limit(4)
            ->get();
@endphp

@if($suggestions->isNotEmpty())
    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <p class="text-sm font-medium text-gray-900 dark:text-white">These articles might already answer this</p>
        <ul class="mt-3 space-y-2">
            @foreach($suggestions as $article)
                <li wire:key="suggest-{{ $article->id }}">
                    <a href="{{ $article->clientUrl() }}" wire:navigate class="text-sm text-primary-700 hover:underline dark:text-primary-400">
                        {{ $article->title }}
                    </a>
                    <span class="text-xs text-gray-400"> · {{ $article->category?->name }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
