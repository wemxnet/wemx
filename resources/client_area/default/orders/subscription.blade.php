@extends('theme::orders.layout', [
    'activeTab' => 'subscription',
])

@section('container')
    @if($order->isNotActive())
        <x-theme::alert.warning text="This order is currently not active, subscription cannot be setup until it's active."/>
    @endif

    @if(!$order->hasActiveSubscription(true) && ! \App\Support\LicensePlanLimits::subscriptionsEnabled())
        <x-theme::alert.warning text="Automatic subscription payments aren't available on this store. You can renew this order manually from its invoices."/>
    @elseif(!$order->hasActiveSubscription(true))
    <x-theme::card class="mb-4">
        <div class="mb-4">
            <h3 class="text-xl font-bold dark:text-white">
                Setup Subscription
            </h3>
            <p class="mt-2 text-sm font-normal text-gray-500 dark:text-gray-400">
                Choose a subscription plan to automatically renew your orders and never miss a payment.
            </p>
        </div>
        <div class="flow-root">
            <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach(App\Models\GatewayConfig::where('type', 'subscription')->where('is_active', true)->get() as $gateway)
                @if($gateway->is_staff_only)
                    @if(!auth()->user() OR !auth()->user()->hasPerm('use-staff-gateways'))
                        @continue
                    @endif
                @endif
                <li class="py-3 sm:py-4">
                    <div class="flex items-center">
                        @if($gateway->icon)
                        <div class="shrink-0">
                            <img class="h-8 w-auto rounded-full" src="{{ $gateway->icon }}" alt="Neil image">
                        </div>
                        @endif
                        <div class="flex-1 min-w-0 ms-4">
                            <p class="text-sm font-medium text-gray-900 truncate dark:text-white">
                                {{ $gateway->display_name }} @if($gateway->is_staff_only) <span class="bg-yellow-100 text-yellow-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded-sm dark:bg-yellow-900 dark:text-yellow-300 ms-1">Staff Only</span> @endif
                            </p>
                            <p class="text-sm text-gray-500 truncate dark:text-gray-400">
                                {{ $gateway->display_description }}
                            </p>
                        </div>
                        <div class="inline-flex items-center text-base font-semibold text-gray-900 dark:text-white">
                            <x-theme::button.primary text="Subscribe" href="{{ route('orders.view.subscription.subscribe', ['order' => $order->id, 'gateway_id' => $gateway->id]) }}"/>
                        </div>
                    </div>
                </li>
                @endforeach
            </ul>
        </div>
    </x-theme::card>
    @endif
    <div class="mb-4">
        @livewire(client_view_path('livewire.table'), [
            'title' => 'Subscription History',
            'description' => 'View the history of subscriptions for this order.',
            'columns' => [
                'Subscription ID',
                'Gateway',
                'Status',
                'Member',
                'Activated At',
                'Next Billing Date',
                'Actions',
            ],
            'rows' =>
                $order->orderSubscriptions()->latest()->get()->map(function($orderSubscription) {

                    if (!$orderSubscription->subscription) {
                        return [
                            '-',
                            '-',
                            'Deleted',
                            '',
                            '',
                            '',
                            '',
                        ];
                    }

                    return [
                        $orderSubscription->subscription->subscription_id,
                        $orderSubscription->subscription->gatewayConfig ? $orderSubscription->subscription->gatewayConfig->display_name : 'None',
                        ucfirst($orderSubscription->status) . ($orderSubscription->subscription->cancelled_at ? ' (Cancelled on ' . $orderSubscription->subscription->cancelled_at->format('Y-m-d') . ')' : ''),
                        $orderSubscription->subscription->user ? $orderSubscription->subscription->user->email : 'Deleted',
                        $orderSubscription->subscription->activated_at ? $orderSubscription->subscription->activated_at->format(settings('date_format', 'd M Y H:i')) : '-',
                        $orderSubscription->subscription->next_billing_at ? $orderSubscription->subscription->next_billing_at->format(settings('date_format', 'd M Y H:i')) : '-',
                       '<a href="'. route('subscriptions.index') .'" class="text-primary-600 dark:text-primary-500 hover:underline" wire:navigate>Manage</a>',
                    ];
                })->toArray(),
        ])
    </div>
@endsection
