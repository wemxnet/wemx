@if(isset($order) && \Extensions\Servers\Proxmox\Server::usesProxmox($order))
    @livewire('client_area.default.orders.livewire.vm-panel', ['order_id' => $order->id])
@endif
