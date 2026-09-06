@extends('admin::layouts.wrapper', [
    'activePage' => 'knowledgebase-categories',
])

@section('title', 'Knowledgebase categories')

@section('actions')
    <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
            @perm('admin.knowledgebase')
                <x-admin::button href="{{ route('admin.knowledgebase.index') }}" color="secondary" wire:navigate>Articles</x-admin::button>
            @endperm
            @perm('admin.knowledgebase.create')
                <x-admin::button href="{{ route('admin.knowledgebase.categories.create') }}" wire:navigate>New category</x-admin::button>
            @endperm
        </div>
    </div>
@endsection

@section('content')
    @livewire('admin_area.default.knowledgebase.livewire.categories-table')
@endsection
