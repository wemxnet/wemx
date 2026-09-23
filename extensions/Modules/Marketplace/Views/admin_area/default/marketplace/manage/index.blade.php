@extends('admin::layouts.wrapper', [
    'activePage' => 'marketplace-manage',
])

@section('title', 'Marketplace resources')

@section('content')
    @livewire('admin_area.default.marketplace.livewire.manage-resources')
@endsection
