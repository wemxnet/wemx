@extends('admin::layouts.wrapper', [
    'activePage' => 'downloads',
])

@section('title', $folder->name)

@section('actions')
    <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
            <x-admin::button href="{{ route('admin.downloads.index') }}" color="secondary" wire:navigate>All folders</x-admin::button>
            @perm('admin.downloads.update')
                <x-admin::button href="{{ route('admin.downloads.folders.edit', $folder) }}" color="secondary" wire:navigate>Edit folder</x-admin::button>
            @endperm
            @perm('admin.downloads.create')
                <x-admin::button href="{{ route('admin.downloads.files.create', $folder) }}" wire:navigate>Upload file</x-admin::button>
            @endperm
        </div>
    </div>
@endsection

@section('content')
    @livewire('admin_area.default.downloads.livewire.folder-files', [
        'folderId' => $folder->id,
    ])
@endsection
