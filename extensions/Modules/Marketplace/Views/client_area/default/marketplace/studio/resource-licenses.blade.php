@extends('marketplace::client_area.default.marketplace.studio.resource-layout', [
    'activeTab' => 'licenses',
    'resource' => $resource,
])

@section('container')
    @livewire('client_area.default.marketplace.livewire.creator-resource-licenses', ['resourceId' => $resource->id])
@endsection
