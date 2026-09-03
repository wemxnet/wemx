@extends('theme::layouts.wrapper', [
    'activePage' => 'tickets',
])

@section('title', 'Tickets')

@section('content')
    <div class="mx-auto max-w-screen-xl px-2 sm:px-4">
        @livewire('client_area.default.tickets.livewire.tickets-table')
    </div>
@endsection
