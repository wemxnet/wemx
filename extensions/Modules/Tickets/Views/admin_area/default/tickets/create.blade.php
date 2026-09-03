@extends('admin::layouts.wrapper', [
    'activePage' => 'tickets',
])

@section('title', 'Create ticket')

@section('content')
    <div class="col-12">
        @livewire('admin_area.default.tickets.livewire.create-ticket-form', [
            'prefillOrderId' => request()->integer('order') ?: null,
            'prefillUserId' => request()->integer('user') ?: null,
        ])
    </div>
@endsection
