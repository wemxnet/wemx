@extends('admin::layouts.wrapper', [
    'activePage' => 'marketplace',
])

@section('title', 'Marketplace')

@section('content')
    @livewire(admin_view_path('integrated-marketplace.livewire.browse'))
@endsection
