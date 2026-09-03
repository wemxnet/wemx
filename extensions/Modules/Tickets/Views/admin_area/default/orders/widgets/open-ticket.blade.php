@if(isset($order) && auth()->user()?->hasPermission('admin.tickets.create'))
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Support</h3>
        </div>
        <div class="card-body">
            <a href="{{ route('admin.tickets.create', ['order' => $order->id]) }}" wire:navigate class="btn w-100">
                Open ticket
            </a>
        </div>
    </div>
@endif
