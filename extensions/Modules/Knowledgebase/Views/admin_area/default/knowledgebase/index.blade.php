@extends('admin::layouts.wrapper', [
    'activePage' => 'knowledgebase',
])

@section('title', 'Knowledgebase')

@section('actions')
    <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
            @perm('admin.knowledgebase')
                <x-admin::button href="{{ route('admin.knowledgebase.categories.index') }}" color="secondary" wire:navigate>Categories</x-admin::button>
            @endperm
            @perm('admin.knowledgebase.create')
                <x-admin::button href="{{ route('admin.knowledgebase.articles.create') }}" wire:navigate>New article</x-admin::button>
            @endperm
        </div>
    </div>
@endsection

@section('content')
    @livewire('admin_area.default.knowledgebase.livewire.articles-overview')
@endsection
