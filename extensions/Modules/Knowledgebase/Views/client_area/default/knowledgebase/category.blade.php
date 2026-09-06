@extends('theme::layouts.wrapper', [
    'activePage' => 'knowledgebase',
])

@section('title', $category->name)

@section('content')
    <div class="mx-auto max-w-screen-xl px-2 sm:px-4">
        @livewire('client_area.default.knowledgebase.livewire.category-view', [
            'categoryId' => $category->id,
        ])
    </div>
@endsection
