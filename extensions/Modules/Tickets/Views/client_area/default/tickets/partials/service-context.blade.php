@php
    $order = $ticket->order;
    $unpaid = $order?->payments?->where('status', 'unpaid') ?? collect();
@endphp

@if($order)
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">{{ __('tickets::messages.service') }}</h3>
        <dl class="space-y-3 text-sm">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Package</dt>
                <dd class="mt-1 text-gray-900 dark:text-white">{{ $order->package->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Status</dt>
                <dd class="mt-1">
                    @if($order->status === 'active')
                        <x-theme::badge.success text="Active"/>
                    @elseif($order->status === 'suspended')
                        <x-theme::badge.warning text="Suspended"/>
                    @elseif($order->status === 'terminated')
                        <x-theme::badge.danger text="Terminated"/>
                    @else
                        <x-theme::badge.warning :text="ucfirst($order->status)"/>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Due date</dt>
                <dd class="mt-1 text-gray-900 dark:text-white">
                    {{ $order->due_date?->format(settings('date_format', 'd M Y')) ?? 'Never' }}
                    @if($order->due_date)
                        <span class="text-gray-500 dark:text-gray-400">({{ $order->due_date->diffForHumans() }})</span>
                    @endif
                </dd>
            </div>
            @if($unpaid->isNotEmpty())
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Unpaid invoices</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white">{{ $unpaid->count() }} · {{ price($unpaid->sum('total')) }}</dd>
                </div>
            @endif
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('tickets::messages.related_order') }}</dt>
                <dd class="mt-1">
                    @auth
                        <a href="{{ route('orders.view', $order) }}" wire:navigate class="text-primary-600 hover:underline dark:text-primary-400">#{{ $order->id }} {{ $order->package->name ?? '' }}</a>
                    @else
                        #{{ $order->id }}
                    @endauth
                </dd>
            </div>
        </dl>
    </div>
@endif
