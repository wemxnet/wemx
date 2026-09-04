<?php

namespace App\Console\Commands\Cronjobs;

use App\Models\AppTaskLog;
use App\Models\Email;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;

class TerminateOrdersPastDueDate extends Command
{
    protected $signature = 'cronjobs:orders:terminate-expired';

    protected $description = 'Terminates suspended orders that have exceeded the grace period after suspension';

    public function handle(): void
    {
        $gracePeriodInDays = settings('terminate_period', 7);
        $orders = Order::getOrdersPastDueDate($gracePeriodInDays)->where('status', 'suspended')->get();

        if ($orders->isEmpty()) {
            $this->info('No orders to terminate.');

            return;
        } else {
            $this->info("Found {$orders->count()} orders to terminate.");
        }

        foreach ($orders as $order) {
            $order->terminateServer();

            $order->log([
                'user_id' => null,
                'action' => 'order_terminated',
                'description' => 'Order has been terminated by system scheduler for being past due date.',
            ]);

            $this->info("Order #{$order->id} has been terminated.");
        }

        // log termination event to application
        AppTaskLog::create([
            'task' => 'terminate_orders_past_due_date',
            'status' => 'completed',
            'message' => "{$orders->count()} orders were terminated for being past due date by {$gracePeriodInDays} days.",
            'show' => true,
            'data' => ['orders' => $orders->pluck('id')->toArray()],
        ]);

        $adminEmail = User::first()->email;

        Email::actions()->sendEmailToAddress([
            'to' => $adminEmail,
            'identifier' => 'admin.orders.terminated_batch',
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
            'button_url' => route('admin.orders.index', ['status' => 'terminated']),
        ]);

        $this->info('Order termination process completed.');
    }
}
