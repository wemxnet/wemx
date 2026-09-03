@extends('admin::layouts.wrapper', [
    'activePage' => 'tickets',
])

@section('title', $ticket->displayNumber().' '.$ticket->title)

@section('content')
    @livewire('admin_area.default.tickets.livewire.ticket-respond', [
        'ticketId' => $ticket->id,
    ])
@endsection
