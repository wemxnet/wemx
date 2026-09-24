@extends('admin::layouts.wrapper', [
    'activePage' => 'marketplace-manager-manage',
])

@section('title', 'Marketplace resources')

@section('content')
    @livewire('admin_area.default.marketplace.livewire.manage-resources')
@endsection
