@if(isset($order) && \Extensions\Servers\Cpanel\Server::usesCpanel($order))
    @livewire('admin_area.default.orders.livewire.account-sidebar', ['order_id' => $order->id])
@endif
