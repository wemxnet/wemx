@extends('admin::layouts.wrapper', [
    'activePage' => 'knowledgebase',
])

@section('title', 'New article')

@section('content')
    <div class="col-12">
        @livewire('admin_area.default.knowledgebase.livewire.article-form')
    </div>
@endsection
