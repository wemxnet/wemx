@extends('theme::layouts.wrapper', [
    'activePage' => 'marketplace',
])

@section('title', __('marketplace::messages.marketplace'))

@section('content')
    <div class="mx-auto max-w-screen-xl px-2 sm:px-4">
        @livewire('client_area.default.marketplace.livewire.browse-marketplace')
    </div>
@endsection
