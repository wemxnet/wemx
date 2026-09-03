@php
    $order = $ticket->order;
    $unpaid = $order?->payments?->where('status', 'unpaid') ?? collect();
    $connection = $order?->package?->serverConnection;
@endphp

@if($order)
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Service</h3>
            <div class="card-actions">
                <a href="{{ route('admin.orders.edit', $order) }}" wire:navigate class="btn btn-sm">View order</a>
            </div>
        </div>
        <div class="card-body">
            <div class="datagrid">
                <div class="datagrid-item">
                    <div class="datagrid-title">Package</div>
                    <div class="datagrid-content">{{ $order->package->name ?? '—' }}</div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">Status</div>
                    <div class="datagrid-content">
                        @if($order->status === 'active')
                            <span class="badge bg-green-lt">Active</span>
                        @elseif($order->status === 'suspended')
                            <span class="badge bg-warning-lt">Suspended</span>
                        @elseif($order->status === 'terminated')
                            <span class="badge bg-danger-lt">Terminated</span>
                        @else
                            <span class="badge bg-yellow-lt">{{ ucfirst($order->status) }}</span>
                        @endif
                    </div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">Due date</div>
                    <div class="datagrid-content">
                        {{ $order->due_date?->format(settings('date_format', 'd M Y')) ?? 'Never' }}
                        @if($order->due_date)
                            <div class="text-secondary">{{ $order->due_date->diffForHumans() }}</div>
                        @endif
                    </div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">Unpaid invoices</div>
                    <div class="datagrid-content">
                        @if($unpaid->isEmpty())
                            <span class="text-secondary">None</span>
                        @else
                            <span class="badge bg-red-lt">{{ $unpaid->count() }}</span>
                            <div class="text-secondary">
                                @foreach($unpaid->take(3) as $payment)
                                    <div>
                                        <a href="{{ route('admin.payments.edit', $payment) }}" wire:navigate>{{ $payment->invoice_id }}</a>
                                        · {{ price($payment->total) }}
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                @if($connection)
                    <div class="datagrid-item">
                        <div class="datagrid-title">Server</div>
                        <div class="datagrid-content">
                            <a href="{{ route('admin.servers.connections.edit', $connection) }}" wire:navigate>
                                {{ $connection->alias }}
                            </a>
                            <div class="text-secondary">{{ $connection->extension_identifier }}</div>
                        </div>
                    </div>
                @endif
                @if($order->external_id)
                    <div class="datagrid-item">
                        <div class="datagrid-title">External ID</div>
                        <div class="datagrid-content"><code>{{ $order->external_id }}</code></div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
