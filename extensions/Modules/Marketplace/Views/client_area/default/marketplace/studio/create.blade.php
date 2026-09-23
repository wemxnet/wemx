@extends('marketplace::client_area.default.marketplace.studio.resource-layout', [
    'activeTab' => 'resource',
    'resource' => null,
])

@section('container')
    @livewire('client_area.default.marketplace.livewire.create-resource-wizard')
@endsection
