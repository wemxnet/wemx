@extends('admin::layouts.wrapper', [
    'activePage' => 'roles',
])

@section('title', 'Roles')

@section('actions')
    <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
            @perm('admin.roles.create')
            <x-admin::button href="{{ route('admin.roles.create') }}" wire:navigate>{{ __('messages.create') }}</x-admin::button>
            @endperm
        </div>
    </div>
@endsection

@section('content')
    {{--  Roles Table  --}}
    @livewire(admin_view_path('livewire.table'), [
        'title' => 'Roles',
        'entries' => 15,
        'columns' => [
            __('messages.id'),
            __('messages.name'),
            'Description',
            'Permissions',
            'Parent Role',
            __('messages.created_at'),
            '',
        ],
        'rows' =>\App\Models\Role::get()->map(function ($role) {
            $canUpdate = auth()->user()->hasPermission('admin.roles.update');

            return [
                $role->id,
                $canUpdate
                    ? '<a href="' . route('admin.roles.edit', $role->id) . '" wire:navigate>' . $role->name . '</a>'
                    : e($role->name),
                $role->description,
                $role->super_admin ? 'All Permissions' : count($role->getAllPermissions()) . ' Permissions',
                $role->parent ? $role->parent->name : '-',
                $role->created_at->format('d M, Y'),
                $canUpdate
                    ? '<a href="' . route('admin.roles.edit', $role->id) . '" wire:navigate>Edit</a>'
                    : '',
            ];
        })->toArray(),
    ])
@endsection
