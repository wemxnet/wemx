<?php

use App\Models\Order;
use Extensions\Servers\Proxmox\Server;
use Extensions\Servers\Proxmox\Support\ProxmoxVmManager;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component
{
    #[Locked]
    public int $order_id;

    #[Computed]
    public function order(): ?Order
    {
        return Order::query()->with('package.serverConnection')->find($this->order_id);
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function usage(): ?array
    {
        $order = $this->order;

        if (! $order || ! Server::usesProxmox($order) || (! $order->external_id && empty($order->data['vmid']))) {
            return null;
        }

        try {
            return ProxmoxVmManager::for($order->package->serverConnection)->status($order);
        } catch (Throwable) {
            return null;
        }
    }
}

?>

@php
    $order = $this->order;
    $usage = $this->usage;
    $account = $order?->getExternalUser();
@endphp

@if($order)
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">{{ __('server-proxmox::messages.admin_title') }}</h3>
        </div>
        <div class="card-body">
            <div class="datagrid">
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('server-proxmox::messages.status') }}</div>
                    <div class="datagrid-content">
                        @if($usage && ($usage['running'] ?? false))
                            <span class="badge bg-green">{{ __('server-proxmox::messages.running') }}</span>
                        @elseif($usage)
                            <span class="badge bg-yellow">{{ __('server-proxmox::messages.stopped') }}</span>
                        @else
                            <span class="badge bg-secondary">{{ $order->external_id ? __('server-proxmox::messages.unknown') : __('server-proxmox::messages.not_provisioned') }}</span>
                        @endif
                    </div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('server-proxmox::messages.hostname') }}</div>
                    <div class="datagrid-content">{{ $order->data['hostname'] ?? '—' }}</div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('server-proxmox::messages.ipv4') }}</div>
                    <div class="datagrid-content">{{ $order->data['ipv4'] ?? __('server-proxmox::messages.dhcp') }}</div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('server-proxmox::messages.vmid') }}</div>
                    <div class="datagrid-content">{{ $order->external_id ?? '—' }}</div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('server-proxmox::messages.node') }}</div>
                    <div class="datagrid-content">{{ $order->data['node'] ?? '—' }}</div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('server-proxmox::messages.username') }}</div>
                    <div class="datagrid-content">{{ $account->username ?? ($order->data['username'] ?? 'root') }}</div>
                </div>
            </div>

            @if($usage)
                <div class="mt-3 small text-secondary">
                    CPU {{ $usage['cpu_percent'] }}%
                    · RAM {{ $usage['memory_percent'] }}%
                    · Disk {{ $usage['disk_percent'] }}%
                </div>
            @endif
        </div>
    </div>
@endif
