@extends('marketplace::client_area.default.marketplace.library.layout', [
    'activeTab' => 'resources',
])

@section('container')
    @livewire('client_area.default.marketplace.livewire.my-resources')
@endsection
