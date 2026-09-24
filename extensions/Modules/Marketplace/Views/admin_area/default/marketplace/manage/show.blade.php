@extends('admin::layouts.wrapper', [
    'activePage' => 'marketplace-manager-manage',
])

@section('title', $resource->name)

@section('content')
    @livewire('admin_area.default.marketplace.livewire.manage-resource', ['resourceId' => $resource->id])
@endsection
