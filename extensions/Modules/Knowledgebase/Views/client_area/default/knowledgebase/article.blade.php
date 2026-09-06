@extends('theme::layouts.wrapper', [
    'activePage' => 'knowledgebase',
])

@section('title', $article->title)

@section('content')
    <div class="mx-auto max-w-screen-xl px-2 sm:px-4">
        @livewire('client_area.default.knowledgebase.livewire.article-view', [
            'articleId' => $article->id,
        ])
    </div>
@endsection
