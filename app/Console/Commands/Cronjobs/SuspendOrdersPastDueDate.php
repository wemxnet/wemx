<?php

namespace App\Console\Commands\Cronjobs;

use App\Models\AppTaskLog;
use App\Models\Email;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;

class SuspendOrdersPastDueDate extends Command
{
    protected $signature = 'cronjobs:orders:suspend-expired';

    protected $description = 'Suspends active orders that are past their due date';

    public function handle(): void
    {
        $gracePeriodInDays = settings('suspend_period', 3);
        $orders = Order::getOrdersPastDueDate($gracePeriodInDays)->where('status', 'active')->get();

        if ($orders->isEmpty()) {
            $this->info('No orders to suspend.');

            return;
        } else {
            $this->info("Found {$orders->count()} orders to suspend.");
        }

        foreach ($orders as $order) {
            $order->suspendServer();

            $order->log([
                'user_id' => null,
                'action' => 'order_suspended',
                'description' => 'Order has been suspended by system scheduler for being past due date.',
            ]);

            $this->info("Order #{$order->id} has been suspended.");
        }

        // log suspension event to application
        AppTaskLog::create([
            'task' => 'suspend_orders_past_due_date',
            'status' => 'completed',
            'message' => "{$orders->count()} orders were suspended for being past due date by {$gracePeriodInDays} days.",
            'show' => true,
            'data' => ['orders' => $orders->pluck('id')->toArray()],
        ]);

        $adminEmail = User::first()->email;

        Email::actions()->sendEmailToAddress([
            'to' => $adminEmail,
            'identifier' => 'admin.orders.suspended_batch',
            'variables' => [
                'count' => $orders->count(),
                'grace_period' => $gracePeriodInDays,
            ],
            'table' => [
                'columns' => [
                    'Order ID',
                    'User',
                    'Package',
                    'Due Date',
                ],
                'rows' => $orders->map(fn ($order) => [
                    $order->id,
                    "{$order->user->email} (ID: {$order->user->id})",
                    $order->package->name,
                    $order->due_date?->toDateString(),
                ])->toArray(),
            ],
            'button_url' => route('admin.orders.index', ['status' => 'suspended']),
        ]);

        $this->info('Order suspension process completed.');
    }
}
