@extends('marketplace::client_area.default.marketplace.studio.resource-layout', [
    'activeTab' => 'resource',
    'resource' => $resource,
])

@section('container')
    @livewire('client_area.default.marketplace.livewire.creator-resource', ['resourceId' => $resource->id])
@endsection
