@extends('admin::layouts.wrapper', [
    'activePage' => 'ticket-departments',
])

@section('title', 'Create department')

@section('content')
    <div class="col-12">
        @livewire('admin_area.default.ticket-departments.livewire.department-form')
    </div>
@endsection
