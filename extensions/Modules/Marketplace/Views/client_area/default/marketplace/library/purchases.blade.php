@extends('marketplace::client_area.default.marketplace.library.layout', [
    'activeTab' => 'purchases',
])

@section('container')
    @livewire('client_area.default.marketplace.livewire.my-purchases')
@endsection
