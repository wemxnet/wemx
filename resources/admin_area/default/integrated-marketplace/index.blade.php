@extends('admin::layouts.wrapper', [
    'activePage' => 'marketplace',
])

@section('title', 'Marketplace')

@section('actions')
    <div class="col-auto ms-auto d-print-none">
        <a href="{{ route('admin.marketplace.installed') }}" wire:navigate class="btn">Installed</a>
    </div>
@endsection

@section('content')
    @livewire(admin_view_path('integrated-marketplace.livewire.browse'))
@endsection
