@extends('theme::layouts.wrapper', [
    'activePage' => 'marketplace',
])

@section('title', __('marketplace::messages.studio'))

@section('content')
    <div class="mx-auto max-w-screen-xl px-2 sm:px-4">
        @include('marketplace::client_area.default.marketplace.partials.studio-nav')
        @livewire('client_area.default.marketplace.livewire.creator-hub')
    </div>
@endsection
