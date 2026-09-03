<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component
{
    public $order;

    #[On('order-updated')]
    public function refreshOrder()
    {

    }
}

?>

@php
    $status = $order->status;

    if($status == "active") {
        $statusColor = "green";
    } elseif($status == "suspended") {
        $statusColor = "orange";
    } elseif($status == "terminated") {
        $statusColor = "red";
    } elseif($status == "failed") {
        $statusColor = "red";
    } else {
        $statusColor = "yellow";
    }
@endphp

@php
    $canUpgradeOrDowngrade = method_exists($order->package->serverConnection->server->functions(), 'upgradeOrDowngrade');
@endphp


<div>
    <div class="row g-3 align-items-center mb-3">
        <div class="col-auto">
                <span class="status-indicator status-{{ $statusColor }} status-indicator-animated">
                  <span class="status-indicator-circle"></span>
                  <span class="status-indicator-circle"></span>
                  <span class="status-indicator-circle"></span>
                </span>
        </div>
        <div class="col">
            <h2 class="page-title">{{ $order->package->name }}</h2>
            <div class="text-secondary">
                <ul class="list-inline list-inline-dots mb-0">
                    <li class="list-inline-item"><span class="text-{{ $statusColor }}">{{ ucfirst($status) }}</span></li>
                    <li class="list-inline-item">
                        Due in
                        <span class="text-secondary">{{ $order->due_date?->diffForHumans() ?? 'Never' }}</span>
                        ({{ $order->due_date?->format('d M Y') ?? 'Never' }})
                    </li>
                </ul>
            </div>
        </div>
        <div class="col-md-auto ms-auto d-print-none">
            <div class="btn-list">
                <button type="button" class="btn" @if($canUpgradeOrDowngrade) data-bs-toggle="offcanvas" data-bs-target="#upgradeOrderDrawer" aria-controls="upgradeOrderDrawer" @else class="btn btn-disabled disabled" disabled @endif>
                    <!-- Download SVG icon from http://tabler.io/icons/icon/settings -->
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-package-export"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 21l-8 -4.5v-9l8 -4.5l8 4.5v4.5" /><path d="M12 12l8 -4.5" /><path d="M12 12v9" /><path d="M12 12l-8 -4.5" /><path d="M15 18h7" /><path d="M19 15l3 3l-3 3" /></svg>
                    Upgrade
                </button>
                <button type="button" class="btn" data-bs-toggle="offcanvas" data-bs-target="#transferOrderDrawer" aria-controls="transferOrderDrawer">
                    <!-- Download SVG icon from http://tabler.io/icons/icon/user-up -->
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-user-up"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4" /><path d="M19 22v-6" /><path d="M22 19l-3 -3l-3 3" /></svg>
                    Transfer
                </button>
                <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#extendOrderDrawer" aria-controls="extendOrderDrawer">
                    <!-- Download SVG icon from http://tabler.io/icons/icon/player-pause -->
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-calendar-week"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12z" /><path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M7 14h.013" /><path d="M10.01 14h.005" /><path d="M13.01 14h.005" /><path d="M16.015 14h.005" /><path d="M13.015 17h.005" /><path d="M7.01 17h.005" /><path d="M10.01 17h.005" /></svg>
                    Extend
                </button>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="mb-3">
                @livewire(admin_view_path('orders.livewire.order-info-card'), ['order' => $order])
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-4">
            <div class="mb-3">
                @livewire(admin_view_path('orders.livewire.order-action-card'), ['order' => $order])
            </div>

            @foreach(extensionElements(['admin-order-sidebar-view']) as $element)
                @includeIf($element['view'], ['order' => $order])
            @endforeach

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Order Members</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">

                            <div class="row g-3 align-items-center">
                                <a href="{{ route('admin.users.edit', $order->user->id) }}" class="col-auto" wire:navigate>
                                <span class="avatar avatar-2" style="background-image: url({{ $order->user->getAvatarUrl() }})">
                                    @if($order->user->isOnline(15))
                                        <span class="badge bg-green"></span>
                                    @endif
                                </a>
                                <div class="col text-truncate">
                                    <a href="{{ route('admin.users.edit', $order->user->id) }}" wire:navigate class="text-reset d-block text-truncate">{{ $order->user->full_name }}</a>
                                    <div class="text-secondary text-truncate mt-n1">
                                        ({{ $order->user->username }}) {{ $order->user->email }}
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                @livewire(admin_view_path('orders.livewire.order-history-card'), ['order' => $order])
            </div>

            <div class="mb-3">
                @livewire(admin_view_path('livewire.log'), ['model' => \App\Models\Order::class, 'model_id' => $order->id])
            </div>
        </div>
        <div class="col-8">

            <div>
                @livewire(admin_view_path('orders.livewire.order-alerts'), ['order' => $order])
            </div>

            <div class="mb-3">
                @if($order->status == 'pending')
                    <x-admin::alerts.warning title="Order is pending activation" message="This order is currently pending activation and will be activated automatically if queue worker is running. You may manually activate it by clicking the button activate button." />
                @endif
                @livewire(admin_view_path('orders.livewire.edit-order-form'), ['order' => $order])
            </div>

        </div>
    </div>
    @livewire(admin_view_path('orders.livewire.extend-order-drawer'), ['order' => $order])
    @livewire(admin_view_path('orders.livewire.transfer-order-drawer'), ['order' => $order])
    @livewire(admin_view_path('orders.livewire.upgrade-order-drawer'), ['order' => $order])
</div>