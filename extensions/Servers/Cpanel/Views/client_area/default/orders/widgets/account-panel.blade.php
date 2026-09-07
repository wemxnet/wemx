@if(isset($order) && \Extensions\Servers\Cpanel\Server::usesCpanel($order))
    @livewire('client_area.default.orders.livewire.account-panel', ['order_id' => $order->id])
@endif
