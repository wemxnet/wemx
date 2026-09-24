@extends('admin::layouts.wrapper', [
    'activePage' => 'marketplace-manager-licenses',
])

@section('title', 'Marketplace licenses')

@section('content')
    @livewire('admin_area.default.marketplace.livewire.manage-licenses')
@endsection
