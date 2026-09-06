@extends('admin::layouts.wrapper', [
    'activePage' => 'downloads',
])

@section('title', 'Downloads')

@section('actions')
    <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
            @perm('admin.downloads.create')
                <x-admin::button href="{{ route('admin.downloads.folders.create') }}" wire:navigate>Create folder</x-admin::button>
            @endperm
        </div>
    </div>
@endsection

@section('content')
    @livewire('admin_area.default.downloads.livewire.folders-table')
@endsection
