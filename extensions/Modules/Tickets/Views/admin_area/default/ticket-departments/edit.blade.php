@extends('admin::layouts.wrapper', [
    'activePage' => 'ticket-departments',
])

@section('title', 'Edit department')

@section('content')
    <div class="col-12">
        @livewire('admin_area.default.ticket-departments.livewire.department-form', [
            'departmentId' => $department->id,
        ])
    </div>
@endsection
