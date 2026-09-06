@extends('admin::layouts.wrapper', [
    'activePage' => 'knowledgebase',
])

@section('title', 'Edit article')

@section('content')
    <div class="col-12">
        @livewire('admin_area.default.knowledgebase.livewire.article-form', [
            'articleId' => $article->id,
        ])
    </div>
@endsection
