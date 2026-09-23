@extends('marketplace::client_area.default.marketplace.studio.resource-layout', [
    'activeTab' => 'versions',
    'resource' => $resource,
])

@section('container')
    @livewire('client_area.default.marketplace.livewire.creator-resource-versions', ['resourceId' => $resource->id])
@endsection
