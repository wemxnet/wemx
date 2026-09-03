@extends('admin::layouts.wrapper', [
    'activePage' => 'dashboard',
])

@section('title', 'Dashboard')

@section('content')

    @php
        $subscriptionStats = [
            'active' => \App\Models\Subscription::all()->filter(fn ($subscription) => $subscription->isActive())->count(),
            'inactive' => \App\Models\Subscription::where('status', 'inactive')->count(),
            'cancelled' => \App\Models\Subscription::where('status', 'cancelled')->count(),
        ];
    @endphp

    @perm('admin.dashboard.statistics')
    <div class="row row-deck row-cards mb-2">
        <!-- Stat Cards Row -->
        @livewire(admin_view_path('dashboard.livewire.new-registrations-stat'))

        @livewire(admin_view_path('dashboard.livewire.revenue-stat'))

        @livewire(admin_view_path('dashboard.livewire.new-orders-stat'))

        @livewire(admin_view_path('dashboard.livewire.recurring-revenue-stat'))
    </div>

    <div class="row row-deck row-cards mb-3">
        <!-- Small Cards Row -->
        @include('admin::dashboard.cards.small-card', [
            'icon' => '
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                     viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M16.7 8a3 3 0 0 0 -2.7 -2h-4a3 3 0 0 0 0 6h4a3 3 0 0 1 0 6h-4a3 3 0 0 1 -2.7 -2"/>
                    <path d="M12 3v3m0 12v3"/>
                </svg>
            ',
            'title' => \App\Helpers\Statistics::paidPaymentCountAllTime() . ' Paid Payments (All Time)',
            'subtitle' => \App\Helpers\Statistics::refundedPaymentCountAllTime() . ' refunded payments',
            'color' => 'primary',
        ])

        @include('admin::dashboard.cards.small-card', [
            'icon' => '
                <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 4m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v2a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z" /><path d="M3 12m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v2a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z" /><path d="M7 8l0 .01" /><path d="M7 16l0 .01" /></svg>
            ',
            'title' => \App\Helpers\Statistics::activeOrderCountAllTime() . ' Active Orders (All Time)',
            'subtitle' => \App\Helpers\Statistics::suspendedOrderCountAllTime() . ' suspended, ' . \App\Helpers\Statistics::terminatedOrderCountAllTime() . ' terminated',
            'color' => 'primary',
        ])

        @include('admin::dashboard.cards.small-card', [
            'icon' => '
                <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" /><path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /><path d="M21 21v-2a4 4 0 0 0 -3 -3.85" /></svg>
            ',
            'title' => \App\Helpers\Statistics::userCountAllTime() . ' Users (All Time)',
            'subtitle' => 'From '. \App\Helpers\Statistics::uniqueCountryCountAllTime() . ' unique countries',
            'color' => 'primary',
        ])

        @include('admin::dashboard.cards.small-card', [
            'icon' => '
                <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 15h-3a1 1 0 0 1 -1 -1v-8a1 1 0 0 1 1 -1h12a1 1 0 0 1 1 1v3" /><path d="M12 19h-4a1 1 0 0 1 -1 -1v-8a1 1 0 0 1 1 -1h12a1 1 0 0 1 1 1v2.5" /><path d="M12 14a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M16 19h6" /><path d="M19 16v6" /></svg>
            ',
            'title' => $subscriptionStats['active'] . ' Active Subscriptions (All Time)',
            'subtitle' => $subscriptionStats['inactive'] . ' inactive, ' . $subscriptionStats['cancelled'] . ' cancelled',
            'color' => 'primary',
        ])
    </div>
    @endperm

    @foreach(extensionElements(['admin-dashboard-top-view']) as $element)
        @includeIf($element['view'])
    @endforeach

    @perm('admin.dashboard.world_map')
    <div class="row row-deck row-cards mb-3">
        <!-- World Map and Visitors List -->
        @include('admin::dashboard.world-map', [
            'countryUsers' => $countryUsers,
        ])

        @include('admin::dashboard.visitors-list', [
            'countryUsers' => $countryUsers,
        ])
    </div>
    @endperm

    <div class="row mb-3">
        <div class="col-8">
            @include('admin::dashboard.partials.system-alerts')

            @foreach(extensionElements(['admin-dashboard-main-view']) as $element)
                @includeIf($element['view'])
            @endforeach

            @perm('admin.dashboard.recent_orders')
            <div class="mb-3">
                {{--  Orders Table  --}}
                @livewire(admin_view_path('livewire.table'), [
                    'title' => __('messages.orders'),
                    'entries' => 6,
                    'columns' => [
                        __('messages.id'),
                        __('messages.order'),
                        'cycle',
                        __('messages.user'),
                        __('messages.status'),
                        'Due Date',
                        '',
                    ],
                    'sortableColumns' => [
                        __('messages.id'),
                        __('messages.amount'),
                        __('messages.status'),
                        __('messages.created_at'),
                    ],
                    'rows' => \App\Models\Order::query()->latest()->get()->map(function ($order) {
                        return [
                            '<a href="' . route('admin.orders.edit', $order->id) . '" wire:navigate>' . $order->id . '</a>',
                            '<div class="d-flex py-1 align-items-center"><img src="' . $order->package->icon() . '" class="avatar me-2" alt="' . $order->package->name . '"><div class="flex-fill"><div class="font-weight-medium"><a href="' . route('admin.orders.edit', $order->id) . '" wire:navigate class="text-reset">' . $order->package->name . '</a></div><div class="text-secondary"><a href="' . route('admin.categories.edit', $order->package->category_id) . '" wire:navigate class="text-reset">' . $order->package->category->name . '</a></div></div></div>',
                            priceIn($order->price, baseCurrency()) . ' / ' . $order->cycle(),
                            $order->user ? '<div class="d-flex py-1 align-items-center"><span class="avatar avatar-2 me-2" style="background-image: url(' . $order->user->getAvatarUrl() . ')"></span><div class="flex-fill"><div class="font-weight-medium"><a href="' . route('admin.users.edit', $order->user_id) . '" wire:navigate class="text-reset">' . $order->user->full_name . ' (' . $order->user->username . ')</a></div><div class="text-secondary"><a href="'. route('admin.users.edit', $order->user_id) .'" wire:navigate class="text-reset">' . $order->user->email . '</a></div></div></div>' : '<span class="badge bg-secondary-lt">Guest</span>',
                            $order->status == 'active' ? '<span class="badge bg-green-lt">Active</span>' : ($order->status == 'suspended' ? '<span class="badge bg-warning-lt">Suspended</span>' : ($order->status == 'terminated' ? '<span class="badge bg-danger-lt">Terminated</span>' : '<span class="badge bg-warning-lt">' . ucfirst($order->status) . '</span>')),
                            $order->due_date?->translatedFormat('d M Y') ?? 'Never',
                            '<a href="' . route('admin.orders.edit', $order->id) . '" wire:navigate>' . __('messages.edit') . '</a>'
                        ];
                    })->toArray(),
                ])
            </div>
            @endperm

            @perm('admin.dashboard.recent_payments')
            <div class="mb-3">
                {{--  Payments Table  --}}
                @livewire(admin_view_path('livewire.table'), [
                    'title' => __('messages.payments'),
                    'entries' => 6,
                    'columns' => [
                        __('messages.id'),
                        __('messages.description'),
                        __('messages.user'),
                        __('messages.amount'),
                        __('messages.status'),
                        __('messages.gateway'),
                        '',
                    ],
                    'sortableColumns' => [
                        __('messages.id'),
                        __('messages.description'),
                        __('messages.amount'),
                        __('messages.status'),
                        __('messages.gateway'),
                    ],
                    'rows' => \App\Models\Payment::query()->where('status', 'paid')->latest()->get()->map(function ($payment) {
                        return [
                            $payment->id,
                            '<a href="' . route('admin.payments.edit', $payment->id) . '" wire:navigate>' . $payment->description . '</a>',
                            $payment->user ? '<div class="d-flex py-1 align-items-center"><span class="avatar avatar-2 me-2" style="background-image: url(' . $payment->user->getAvatarUrl() . ')"></span><div class="flex-fill"><div class="font-weight-medium"><a href="' . route('admin.users.edit', $payment->user_id) . '" wire:navigate class="text-reset">' . $payment->user->full_name . ' (' . $payment->user->username . ')</a></div><div class="text-secondary"><a href="'. route('admin.users.edit', $payment->user_id) .'" wire:navigate class="text-reset">' . $payment->user->email . '</a></div></div></div>' : '<span class="badge bg-secondary-lt">Guest</span>',
                            priceIn($payment->total(), $payment->currency) . ' '. $payment->currency,
                            $payment->status == 'paid' ? '<span class="badge bg-green-lt">Paid</span>' : ($payment->status == 'unpaid' ? '<span class="badge bg-danger-lt">Unpaid</span>' : '<span class="badge bg-info-lt">' . ucfirst($payment->status) . '</span>'),
                            $payment->gatewayConfig ? $payment->gatewayConfig->display_name : '<span class="badge bg-secondary-lt">None</span>',
                            '<a href="' . route('admin.payments.edit', $payment->id) . '" wire:navigate>' . __('messages.edit') . '</a>'
                        ];
                    })->toArray(),
                ])
            </div>
            @endperm
        </div>
        @php
            $onlineUsers = \App\Models\User::where('last_seen_at', '>=', now()->subMinutes(5))->get();
        @endphp
        <div class="col-4 flex-column">
            @foreach(extensionElements(['admin-dashboard-sidebar-view']) as $element)
                @includeIf($element['view'])
            @endforeach

            @perm('admin.dashboard.online_users')
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Online Users ({{ $onlineUsers->count() }})</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3">

                        @foreach($onlineUsers as $user)
                            <div class="col-6">
                                <div class="row g-3 align-items-center">
                                    <a href="{{ route('admin.users.edit', $user->id) }}" class="col-auto">
                                    <span class="avatar avatar-2" style="background-image: url({{ $user->getAvatarUrl() }})">
                                        <span class="badge bg-green"></span>
                                    </span>
                                    </a>
                                    <div class="col text-truncate">
                                        <a href="{{ route('admin.users.edit', $user->id) }}" wire:navigate class="text-reset d-block text-truncate">{{ $user->username }}</a>
                                        <div class="text-secondary text-truncate mt-n1">
                                            {{ $user->last_seen_at->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endperm

            @perm('admin.dashboard.application_logs')
            <div class="mb-3">
                @livewire(admin_view_path('dashboard.livewire.live-events-timeline'))
            </div>

            <ul class="timeline">
                @foreach(\App\Models\AppTaskLog::where('show', true)->latest()->limit(5)->get() as $log)
                <li class="timeline-event">
                    <div class="timeline-event-icon bg-twitter-lt"><!-- SVG icon from http://tabler.io/icons/icon/brand-twitter -->
                        <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-clock-cog"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M21 12a9 9 0 1 0 -9.002 9" /><path d="M19.001 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M19.001 15.5v1.5" /><path d="M19.001 21v1.5" /><path d="M22.032 17.25l-1.299 .75" /><path d="M17.27 20l-1.3 .75" /><path d="M15.97 17.25l1.3 .75" /><path d="M20.733 20l1.3 .75" /><path d="M12 7v5l2 2" /></svg>                    </div>
                    <div class="card timeline-event-card">
                        <div class="card-body">
                            <div class="text-secondary float-end">{{ $log->created_at->diffForHumans() }}</div>
                            <h4>
                                {{ $log->task }}
                            </h4>
                            <p class="text-secondary">{{ $log->message }}</p>
                            <p><a href="{{ route('admin.schedule-logs.index') }}" wire:navigate>View logs</a></p>
                        </div>
                    </div>
                </li>
                @endforeach
            </ul>
            @endperm

        </div>
    </div>
@endsection