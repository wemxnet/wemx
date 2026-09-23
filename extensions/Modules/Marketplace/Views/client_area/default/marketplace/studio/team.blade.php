@extends('marketplace::client_area.default.marketplace.studio.resource-layout', [
    'activeTab' => 'team',
    'resource' => $resource,
])

@section('container')
    @livewire('client_area.default.marketplace.livewire.creator-resource-team', ['resourceId' => $resource->id])
@endsection
