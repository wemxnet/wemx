@extends('admin::layouts.wrapper', [
    'activePage' => 'knowledgebase-categories',
])

@section('title', 'Edit category')

@section('content')
    <div class="col-12">
        @livewire('admin_area.default.knowledgebase.livewire.category-form', [
            'categoryId' => $category->id,
        ])
    </div>
@endsection
