@extends('admin::layouts.wrapper', [
    'activePage' => 'marketplace-licenses',
])

@section('title', 'Marketplace licenses')

@section('content')
    @livewire('admin_area.default.marketplace.livewire.manage-licenses')
@endsection
