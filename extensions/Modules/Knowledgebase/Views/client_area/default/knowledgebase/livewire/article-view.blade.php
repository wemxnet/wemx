<?php

use Extensions\Modules\Knowledgebase\Models\KnowledgebaseArticle;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public int $articleId;

    public ?bool $voted = null;

    public function mount(): void
    {
        $article = $this->article;
        abort_unless($article->isVisibleTo(auth()->user()), 404);

        KnowledgebaseArticle::actions()->recordView($article, auth()->user());

        $existing = $article->votes()
            ->where('visitor_hash', KnowledgebaseArticle::visitorHash(auth()->user()))
            ->first();

        $this->voted = $existing?->is_helpful;
    }

    #[Computed]
    public function article(): KnowledgebaseArticle
    {
        return KnowledgebaseArticle::query()
            ->with(['category.parent'])
            ->findOrFail($this->articleId);
    }

    public function vote(bool $helpful): void
    {
        $vote = KnowledgebaseArticle::actions()->vote([
            'article_id' => $this->articleId,
            'user_id' => auth()->id(),
            'is_helpful' => $helpful,
        ]);

        $this->voted = $vote->is_helpful;
        unset($this->article);
    }
}

?>

@php
    $article = $this->article;
    $category = $article->category;
    $toc = $article->tableOfContents();
    $related = $article->related(auth()->user());
    $article->refresh();
@endphp

<article>
    <nav class="mb-6 text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('knowledgebase.index') }}" wire:navigate class="hover:text-gray-900 dark:hover:text-white">{{ __('knowledgebase::messages.knowledgebase') }}</a>
        @foreach($category->breadcrumbs() as $crumb)
            <span class="px-1.5">/</span>
            <a href="{{ $crumb->clientUrl() }}" wire:navigate class="hover:text-gray-900 dark:hover:text-white">{{ $crumb->name }}</a>
        @endforeach
        <span class="px-1.5">/</span>
        <span class="text-gray-900 dark:text-white">{{ $article->title }}</span>
    </nav>

    <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_14rem]">
        <div class="min-w-0">
            @if(! $article->is_published)
                <p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">This article is a draft. Only staff can see it.</p>
            @endif

            <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $article->title }}</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('knowledgebase::messages.updated') }} {{ $article->updated_at?->toFormattedDateString() }}
                · {{ number_format($article->views_count) }} {{ __('knowledgebase::messages.views') }}
                @if($article->hidden_from_guests)
                    · Clients only
                @endif
            </p>

            @if(filled($article->tags))
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($article->tags as $tag)
                        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $tag }}</span>
                    @endforeach
                </div>
            @endif

            <div class="format format-blue dark:format-invert mt-8 max-w-none">
                {!! $article->renderedContent() !!}
            </div>

            <div class="mt-10 rounded-xl border border-gray-200 bg-gray-50 px-5 py-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('knowledgebase::messages.was_this_helpful') }}</p>
                <div class="mt-3 flex gap-2">
                    <button
                        type="button"
                        wire:click="vote(true)"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $voted === true ? 'bg-primary-700 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-600' }}"
                    >{{ __('knowledgebase::messages.yes') }}</button>
                    <button
                        type="button"
                        wire:click="vote(false)"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $voted === false ? 'bg-primary-700 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-600' }}"
                    >{{ __('knowledgebase::messages.no') }}</button>
                </div>
                @if($article->helpful_count + $article->unhelpful_count > 0)
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $article->helpfulPercent() }}% found this helpful</p>
                @endif
            </div>

            @if($related->isNotEmpty())
                <div class="mt-10">
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('knowledgebase::messages.related') }}</h2>
                    <ul class="divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 bg-white dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                        @foreach($related as $relatedArticle)
                            <li wire:key="related-{{ $relatedArticle->id }}">
                                <a href="{{ $relatedArticle->clientUrl() }}" wire:navigate class="block px-4 py-3 text-sm font-medium text-gray-900 hover:bg-gray-50 dark:text-white dark:hover:bg-gray-900/40">
                                    {{ $relatedArticle->title }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @if($toc)
            <aside class="hidden lg:block">
                <div class="sticky top-24">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('knowledgebase::messages.on_this_page') }}</p>
                    <nav class="space-y-1.5 border-l border-gray-200 pl-3 dark:border-gray-700">
                        @foreach($toc as $item)
                            <a href="#{{ $item['id'] }}" class="block text-sm {{ $item['level'] === 3 ? 'pl-3 text-gray-500' : 'text-gray-700 dark:text-gray-300' }} hover:text-primary-700 dark:hover:text-primary-400">
                                {{ $item['text'] }}
                            </a>
                        @endforeach
                    </nav>
                </div>
            </aside>
        @endif
    </div>
</article>
