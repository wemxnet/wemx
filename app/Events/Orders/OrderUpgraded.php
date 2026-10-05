<?php

namespace App\Events\Orders;

use App\Models\Order;
use App\Models\PackagePrice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderUpgraded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Order $order,
        public ?PackagePrice $previousPackagePrice,
        public PackagePrice $packagePrice,
    ) {}
}
