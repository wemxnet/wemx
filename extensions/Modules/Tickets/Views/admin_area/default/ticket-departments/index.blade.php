@extends('admin::layouts.wrapper', [
    'activePage' => 'ticket-departments',
])

@section('title', 'Ticket departments')

@section('actions')
    <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
            @perm('admin.ticket-departments.create')
                <x-admin::button href="{{ route('admin.ticket-departments.create') }}" wire:navigate>{{ __('messages.create') }}</x-admin::button>
            @endperm
        </div>
    </div>
@endsection

@section('content')
    @perm('admin.ticket-departments')
        @livewire('admin_area.default.ticket-departments.livewire.inbound-mail-settings')
    @endperm
    @livewire(admin_view_path('livewire.table'), [
        'title' => 'Departments',
        'entries' => 25,
        'columns' => [
            __('messages.id'),
            __('messages.name'),
            'Guests',
            'Invites',
            'Tickets',
            'Status',
            '',
        ],
        'sortableColumns' => [
            __('messages.id'),
            __('messages.name'),
        ],
        'rows' => \Extensions\Modules\Tickets\Models\TicketDepartment::query()->ordered()->withCount('tickets')->get()->map(function ($department) {
            return [
                $department->id,
                '<a href="'.route('admin.ticket-departments.edit', $department).'" wire:navigate>'.$department->name.'</a><div class="text-secondary">'.$department->slug.'</div>',
                $department->allow_guest_tickets ? '<span class="badge bg-green-lt">Create</span>' : '<span class="badge bg-secondary-lt">No</span>'.($department->allow_guest_members ? ' <span class="badge bg-blue-lt">Members</span>' : ''),
                $department->allow_invites ? '<span class="badge bg-green-lt">On</span>' : '<span class="badge bg-secondary-lt">Off</span>',
                $department->tickets_count,
                $department->is_active ? '<span class="badge bg-green-lt">Active</span>' : '<span class="badge bg-secondary-lt">Inactive</span>',
                '<a href="'.route('admin.ticket-departments.edit', $department).'" wire:navigate>'.__('messages.edit').'</a>',
            ];
        })->toArray(),
    ])
@endsection
