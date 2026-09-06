@extends('admin::layouts.wrapper', [
    'activePage' => 'downloads',
])

@section('title', 'Edit file')

@section('content')
    <div class="col-12">
        @livewire('admin_area.default.downloads.livewire.file-form', [
            'fileId' => $file->id,
            'folderId' => $file->folder_id,
        ])
    </div>
@endsection
