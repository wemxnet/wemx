@extends('admin::layouts.wrapper', [
    'activePage' => 'tickets',
])

@section('title', 'Support tickets')

@section('actions')
    <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
            @perm('admin.ticket-departments')
                <x-admin::button href="{{ route('admin.ticket-departments.index') }}" color="secondary" wire:navigate>Departments</x-admin::button>
            @endperm
            @perm('admin.tickets.create')
                <x-admin::button href="{{ route('admin.tickets.create') }}" wire:navigate>Create ticket</x-admin::button>
            @endperm
        </div>
    </div>
@endsection

@section('content')
    @livewire('admin_area.default.tickets.livewire.tickets-overview')
@endsection
