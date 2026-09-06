@extends('admin::layouts.wrapper', [
    'activePage' => 'downloads',
])

@section('title', 'Edit folder')

@section('content')
    <div class="col-12">
        @livewire('admin_area.default.downloads.livewire.folder-form', [
            'folderId' => $folder->id,
        ])
    </div>
@endsection
