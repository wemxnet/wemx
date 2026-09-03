@extends('theme::layouts.wrapper', [
    'activePage' => 'tickets',
])

@section('title', 'New ticket')

@section('content')
    <div class="mx-auto max-w-3xl px-2 sm:px-4">
        @livewire('client_area.default.tickets.livewire.create-ticket-form', [
            'prefillOrderId' => request()->integer('order') ?: null,
        ])
    </div>
@endsection
