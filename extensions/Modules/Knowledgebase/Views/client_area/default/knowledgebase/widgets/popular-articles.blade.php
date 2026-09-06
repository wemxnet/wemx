@php
    $articles = \Extensions\Modules\Knowledgebase\Models\KnowledgebaseArticle::query()
        ->with('category')
        ->visibleTo(auth()->user())
        ->popular()
        ->limit(4)
        ->get();
@endphp

@if($articles->isNotEmpty())
    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('knowledgebase::messages.popular') }}</h2>
            <a href="{{ route('knowledgebase.index') }}" wire:navigate class="text-sm text-primary-700 hover:underline dark:text-primary-400">{{ __('knowledgebase::messages.browse_docs') }}</a>
        </div>
        <ul class="space-y-2">
            @foreach($articles as $article)
                <li>
                    <a href="{{ $article->clientUrl() }}" wire:navigate class="text-sm text-gray-700 hover:text-primary-700 dark:text-gray-300 dark:hover:text-primary-400">
                        {{ $article->title }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
