@extends('admin::layouts.wrapper', [
    'activePage' => 'knowledgebase-categories',
])

@section('title', 'New category')

@section('content')
    <div class="col-12">
        @livewire('admin_area.default.knowledgebase.livewire.category-form')
    </div>
@endsection
