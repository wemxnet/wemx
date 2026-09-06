@extends('admin::layouts.wrapper', [
    'activePage' => 'data-export',
])

@section('title', __('messages.data_export'))

@section('content')
    @livewire(admin_view_path('data-export.livewire.export-dashboard'))
@endsection
