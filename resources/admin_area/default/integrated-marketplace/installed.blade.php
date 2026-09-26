@extends('admin::layouts.wrapper', [
    'activePage' => 'marketplace_installed',
])

@section('title', 'Installed')

@section('actions')
    <div class="col-auto ms-auto d-print-none">
        <a href="{{ route('admin.marketplace.index') }}" wire:navigate class="btn">Browse</a>
    </div>
@endsection

@section('content')
    @livewire(admin_view_path('integrated-marketplace.livewire.installed'))
@endsection
