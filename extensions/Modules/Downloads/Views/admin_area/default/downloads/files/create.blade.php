@extends('admin::layouts.wrapper', [
    'activePage' => 'downloads',
])

@section('title', 'Upload file')

@section('content')
    <div class="col-12">
        @livewire('admin_area.default.downloads.livewire.file-form', [
            'folderId' => $folder->id,
        ])
    </div>
@endsection
