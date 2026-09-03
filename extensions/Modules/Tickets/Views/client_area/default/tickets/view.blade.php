@extends('theme::layouts.wrapper', [
    'activePage' => 'tickets',
])

@section('title', $ticket->title)

@section('content')
    <div class="mx-auto max-w-screen-xl px-2 sm:px-4">
        @livewire('client_area.default.tickets.livewire.ticket-view', [
            'ticketId' => $ticket->id,
            'guestToken' => $guestToken ?? null,
            'memberToken' => $memberToken ?? null,
        ])
    </div>
@endsection
