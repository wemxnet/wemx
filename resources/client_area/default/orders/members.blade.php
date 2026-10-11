@extends('theme::orders.layout', [
    'activeTab' => 'members',
])

@section('container')
    <div class="mb-4 flex items-center justify-stretch sm:justify-end">
        <x-theme::button.primary class="w-full sm:w-auto" type="button" data-drawer-target="invite-order-member-drawer" data-drawer-show="invite-order-member-drawer" data-drawer-placement="right" aria-controls="invite-order-member-drawer" text="Invite Member" />
    </div>
    <div class="mb-4">
        @livewire(client_view_path('livewire.table'), [
            'title' => 'Members',
            'description' => 'View all members associated with this order.',
            'columns' => [
                'Email',
                'Status',
                'Role',
                'Invited On',
                'Joined On',
                'Actions',
            ],
            'rows' =>
                $order->members()->latest()->get()->map(function($member) {
                    return [
                        $member->email,
                        $member->status,
                        'Administrator',
                        $member->created_at->format('d M Y'),
                        $member->status == 'active' ? $member->updated_at->format('d M Y') : 'Never',
                        '<a href="' . route('orders.invites.remove', ['member_id' => $member->id]) . '" class="text-red-600 hover:text-red-900 dark:text-red-500 dark:hover:text-red-600" wire:navigate>Remove</a>',
                    ];
                })->toArray(),
        ])
    </div>

    @livewire(client_view_path('orders.livewire.invite-member-drawer'), [
        'order_id' => $order->id,
        'order_status' => $order->status,
    ])
@endsection
