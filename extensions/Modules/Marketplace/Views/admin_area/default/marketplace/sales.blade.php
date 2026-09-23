@extends('admin::layouts.wrapper', [
    'activePage' => 'marketplace-sales',
])

@section('title', 'Marketplace sales')

@section('content')
    @livewire('admin_area.default.marketplace.livewire.manage-sales')
@endsection
