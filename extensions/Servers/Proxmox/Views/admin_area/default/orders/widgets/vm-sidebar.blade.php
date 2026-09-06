@if(isset($order) && \Extensions\Servers\Proxmox\Server::usesProxmox($order))
    @livewire('admin_area.default.orders.livewire.vm-sidebar', ['order_id' => $order->id])
@endif
