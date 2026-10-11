@extends('theme::dashboard.dashboard-layout')

@section('container')
    @if($inviteCount = \App\Models\OrderMember::where('email', auth()->user()->email)->where('status', 'pending')->count() > 0)
    <div>
        <x-theme::alert.primary class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
            <span class="min-w-0">You have {{ $inviteCount }} pending invite(s) to orders.</span>
            <x-theme::button.primary class="w-full justify-center sm:w-auto" href="{{ route('dashboard.order-invites') }}" wire:navigate text="View Invites" />
        </x-theme::alert.primary>
    </div>
    @endif

    @foreach(auth()->user()->orders()->where('status', 'active')->whereNotNull('due_date')->where('due_date', '<', now()->addDays(5))->get() as $order)
        <div>
            <x-theme::alert.warning class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
            <span class="min-w-0">Order {{ $order->package->name }} (#{{ $order->id }}) is due in {{ $order->due_date->diffForHumans() }}, please renew it in time to avoid suspension.</span>
                <x-theme::button.primary class="w-full shrink-0 justify-center sm:w-auto" href="{{ route('orders.view', $order->id) }}" wire:navigate text="View Order"/>
            </x-theme::alert.warning>
        </div>
    @endforeach

    @foreach(auth()->user()->orders()->where('status', 'suspended')->get() as $order)
        <div>
            <x-theme::alert.danger class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
            <span class="min-w-0">Order {{ $order->package->name }} (#{{ $order->id }}) is suspended, please renew it in time to avoid termination.</span>
                <x-theme::button.primary class="w-full shrink-0 justify-center sm:w-auto" href="{{ route('orders.view', $order->id) }}" wire:navigate text="View Order"/>
            </x-theme::alert.danger>
        </div>
    @endforeach

    <div class="mb-4">
        @livewire(client_view_path('orders.livewire.orders-table'))
    </div>

    <div class="mb-4">
        @livewire(client_view_path('livewire.table'), [
            'title' => 'Payments',
            'description' => 'View your recent successful payments.',
            'perPage' => 5,
            'columns' => [
                'Description',
                'Gateway',
                'Transaction ID',
                'Amount',
                'Currency',
                'Status',
                'Paid At',
                'Actions',
            ],
            'rows' =>
                auth()->user()->payments()->latest()->whereIn('status', ['paid', 'refunded'])->get()->map(function($payment) {
                    return [
                        Str::limit($payment->description, 50),
                        $payment->gatewayConfig ? $payment->gatewayConfig->display_name : 'None',
                        $payment->transaction_id ? Str::limit($payment->transaction_id, 32) : '-',
                        priceIn($payment->total(), $payment->currency),
                        $payment->currency,
                        ucfirst($payment->status),
                        $payment->paid_at ? $payment->paid_at->format(settings('date_format', 'd M Y H:i')) : '-',
                        '<a href="'. route('payments.view', $payment->token) .'" wire:navigate class="font-medium text-blue-600 dark:text-blue-500 hover:underline">View</a>',
                    ];
                })->toArray(),
        ])
    </div>
@endsection
