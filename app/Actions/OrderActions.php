<?php

namespace App\Actions;

use App\Events\Orders\OrderRenewed;
use App\Handlers\Subscriptions\OrderSubscriptionHandler;
use App\Models\GatewayConfig;
use App\Models\Order;
use App\Models\OrderMember;
use App\Models\PackagePrice;
use App\Models\Subscription;
use App\Models\User;
use App\Support\LicensePlanLimits;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderActions extends Action
{
    /**
     * This function creates a new order as an admin.
     *
     *
     * @return Order
     *
     * @throws ValidationException
     */
    public function createOrderAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'package_price_id' => ['required', 'exists:package_prices,id'],
            'due_date' => ['nullable', 'date'],
            'create_server_instance' => ['required', 'boolean'],
            'email_order_confirmation' => ['required', 'boolean'],
            'config_options' => ['sometimes', 'array'],
        ])->validate();

        $price = PackagePrice::find($validatedData['package_price_id']);

        if (! $price) {
            throw ValidationException::withMessages([
                'package_price_id' => 'Price not found',
            ]);
        }

        if ($price->package->global_quantity !== -1 && $price->package->global_quantity <= 0) {
            throw ValidationException::withMessages([
                'package_price_id' => 'This package is out of stock.',
            ]);
        }

        LicensePlanLimits::assertCanCreateOrders(1, 'package_price_id');

        // if due date is not provided, and price is recurring we calculate it
        if (! isset($validatedData['due_date']) && $price->isRecurring()) {
            $validatedData['due_date'] = now()->addDays($price->period_in_days);
        }

        $orderData = [
            'user_id' => $validatedData['user_id'],
            'package_id' => $price->package_id,
            'package_price_id' => $price->id,
            'status' => 'pending',
            'cycle_price' => $price->getDailyPrice(),
            'setup_fee' => $price->setup_fee,
            'upgrade_fee' => $price->upgrade_fee,
            'period_in_days' => $price->period_in_days,
            'last_renewed_at' => now(),
            'due_date' => $validatedData['due_date'] ?? null,
        ];

        $order = Order::create($orderData);

        if ($price->package->global_quantity !== -1) {
            $price->package->decrement('global_quantity', 1);
        }

        // calculate breakdown of config options
        if (isset($validatedData['config_options'])) {
            $configOptionBreakdown = $price->package->configurableOptionCalculator($validatedData['config_options'], $price->period_in_days);

            if (! empty($configOptionBreakdown) && isset($configOptionBreakdown['breakdown']) && is_array($configOptionBreakdown['breakdown'])) {
                foreach ($configOptionBreakdown['breakdown'] as $option) {
                    $order->prices()->create([
                        'description' => $option['label'],
                        'type' => 'config_option',
                        'key' => $option['key'],
                        'value' => $option['value'],
                        'cycle_price' => $option['daily_price'],
                        'setup_fee' => 0,
                        'upgrade_fee' => 0,
                    ]);
                }
            }
        }

        if ($validatedData['create_server_instance']) {
            $order->createServer(
                dispatch: false,
            );
        } else {
            $order->update(['status' => 'active']);
        }

        if ($validatedData['email_order_confirmation']) {
            // Send email confirmation logic here
        }

        $order->log([
            'user_id' => auth()->check() ? auth()->id() : null,
            'action' => 'order_created_as_admin',
            'description' => 'Order has been created manually by '.(auth()->check() ? auth()->user()->username : 'system'),
        ]);

        // finally, we email the user about the order creation
        if ($validatedData['email_order_confirmation']) {
            $order->emailOrderConfirmation();
        }

        return $order;
    }

    public function suspendOrderAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
        ])->validate();

        $order = Order::find($validatedData['order_id']);

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => 'Order not found',
            ]);
        }

        $order->suspendServer(
            dispatch: false,
        );

        $order->log([
            'user_id' => auth()->check() ? auth()->id() : null,
            'action' => 'order_suspended',
            'description' => 'Order has been suspended manually by '.(auth()->check() ? auth()->user()->username : 'system'),
        ]);

        return $order;
    }

    public function unsuspendOrderAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
        ])->validate();

        $order = Order::find($validatedData['order_id']);

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => 'Order not found',
            ]);
        }

        $order->unsuspendServer(
            dispatch: false,
        );

        $order->log([
            'user_id' => auth()->check() ? auth()->id() : null,
            'action' => 'order_unsuspended',
            'description' => 'Order has been unsuspended manually by '.(auth()->check() ? auth()->user()->username : 'system'),
        ]);

        return $order;
    }

    public function terminateOrderAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
        ])->validate();

        $order = Order::find($validatedData['order_id']);

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => 'Order not found',
            ]);
        }

        $order->terminateServer(
            dispatch: false,
        );

        $order->log([
            'user_id' => auth()->check() ? auth()->id() : null,
            'action' => 'order_terminated',
            'description' => 'Order has been terminated manually by '.(auth()->check() ? auth()->user()->username : 'system'),
        ]);

        return $order;
    }

    public function activatePendingOrderAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
        ])->validate();

        $order = Order::find($validatedData['order_id']);

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => 'Order not found',
            ]);
        }

        if ($order->status !== 'pending') {
            throw ValidationException::withMessages([
                'order_id' => 'Only pending orders can be activated manually.',
            ]);
        }

        $order->createServer(
            dispatch: false,
        );

        $order->log([
            'user_id' => auth()->check() ? auth()->id() : null,
            'action' => 'order_activated',
            'description' => 'Order has been activated manually by '.(auth()->check() ? auth()->user()->username : 'system'),
        ]);

        return $order;
    }

    public function extendOrderAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'due_date' => ['required', 'date'],
            'email_body' => ['nullable', 'string'],
        ])->validate();

        $order = Order::find($validatedData['order_id']);

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => 'Order not found',
            ]);
        }

        $order->update([
            'due_date' => $validatedData['due_date'],
        ]);

        // todo: send email notification if email_body is provided
        if (isset($validatedData['email_body']) && ! empty($validatedData['email_body'])) {
            $order->sendOrderEmail('order.extended', [
                'message' => $validatedData['email_body'],
            ]);
        }

        $order->log([
            'user_id' => auth()->check() ? auth()->id() : null,
            'action' => 'order_extended',
            'description' => 'Order has been extended manually by '.(auth()->check() ? auth()->user()->username : 'system'),
        ]);

        return $order;
    }

    public function transferOrderAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'email_body' => ['nullable', 'string'],
        ])->validate();

        $order = Order::find($validatedData['order_id']);

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => 'Order not found',
            ]);
        }

        $user = User::find($validatedData['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        $order->update([
            'user_id' => $user->id,
        ]);

        $order->log([
            'user_id' => auth()->check() ? auth()->id() : null,
            'action' => 'order_transferred',
            'description' => 'Order has been transferred manually by '.(auth()->check() ? auth()->user()->username : 'system').' from user #'.$order->user->id.' to user #'.$user->id,
        ]);

        if (isset($validatedData['email_body']) && ! empty($validatedData['email_body'])) {
            $user->email([
                'identifier' => 'order.transferred',
                'mailable_type' => Order::class,
                'mailable_id' => $order->id,
                'variables' => array_merge($order->orderEmailVariables(), [
                    'message' => $validatedData['email_body'],
                ]),
                'table' => $order->orderEmailTable(),
                'button' => [
                    'url' => route('orders.view', ['order' => $order->id]),
                ],
            ]);
        }

        return $order;
    }

    public function upgradeOrderAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'package_price_id' => ['required', 'exists:package_prices,id'],
        ])->validate();

        $order = Order::find($validatedData['order_id']);
        $newPackagePrice = PackagePrice::with('package')->find($validatedData['package_price_id']);

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => 'Order not found',
            ]);
        }

        if (! $newPackagePrice) {
            throw ValidationException::withMessages([
                'package_price_id' => 'Selected package price not found',
            ]);
        }

        if ((int) $order->package_price_id === (int) $newPackagePrice->id) {
            throw ValidationException::withMessages([
                'package_price_id' => 'Please select a different package price.',
            ]);
        }

        if ((int) $order->package->connection_id !== (int) $newPackagePrice->package->connection_id) {
            throw ValidationException::withMessages([
                'package_price_id' => 'You can only upgrade/downgrade within packages on the same server connection.',
            ]);
        }

        $serverFunctions = $order->package->serverConnection->server->functions();
        if (! method_exists($serverFunctions, 'upgradeOrDowngrade')) {
            throw ValidationException::withMessages([
                'package_price_id' => 'This server connection does not support upgrade/downgrade operations.',
            ]);
        }

        $oldPackagePrice = PackagePrice::find($order->package_price_id);

        $order->update([
            'package_id' => $newPackagePrice->package_id,
            'package_price_id' => $newPackagePrice->id,
            'cycle_price' => $newPackagePrice->getDailyPrice(),
            'setup_fee' => $newPackagePrice->setup_fee,
            'upgrade_fee' => $newPackagePrice->upgrade_fee,
            'period_in_days' => $newPackagePrice->period_in_days,
        ]);

        $order->refresh();

        try {
            $connection = $order->package->serverConnection;

            $serverFunctions->upgradeOrDowngrade($order, $oldPackagePrice, $newPackagePrice, $connection);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'package_price_id' => 'Failed to run upgrade on server connection: '.$e->getMessage(),
            ]);
        }

        $order->log([
            'user_id' => auth()->check() ? auth()->id() : null,
            'action' => 'order_upgraded',
            'description' => 'Order upgraded manually by '.(auth()->check() ? auth()->user()->username : 'system').' to package price #'.$newPackagePrice->id,
        ]);

        return $order;
    }

    /**
     * Create the order from a package price for the client
     *
     * @throws ValidationException
     */
    public static function createOrderFromPackagePriceForClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'package_price_id' => ['required', 'integer', 'exists:package_prices,id'],
        ])->validate();

        $price = PackagePrice::find($validatedData['package_price_id']);

        if (! $price) {
            throw ValidationException::withMessages([
                'package_price_id' => 'Price not found',
            ]);
        }

        LicensePlanLimits::assertCanCreateOrders(1, 'package_price_id');

        $orderData = [
            'user_id' => $validatedData['user_id'],
            'package_id' => $price->package_id,
            'status' => 'pending',
            'period_in_days' => $price->period_in_days,
            'last_renewed_at' => now(),
        ];

        if ($price->isRecurring()) {
            $orderData['due_date'] = now()->addDays($price->period_in_days);
        }

        $order = Order::create($orderData);

        $order->prices()->create([
            'package_price_id' => $price->id,
            'daily_price' => $price->getDailyPrice(),
            'setup_fee' => $price->setup_fee,
            'upgrade_fee' => $price->upgrade_fee,
        ]);

        $order->createServer(
            dispatch: true,
        );

        return $order;
    }

    public function renewOrderAsClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'renewal_days' => ['required', 'integer', 'min:1'],
        ])->validate();

        $order = Order::find($validatedData['order_id']);

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => 'Order not found',
            ]);
        }

        $order->update([
            'due_date' => ($order->due_date ?? now())->addDays($validatedData['renewal_days']),
            'last_renewed_at' => now(),
        ]);

        $order->log([
            'user_id' => $order->user_id,
            'action' => 'order_extended',
            'description' => "Order has been renewed by {$order->user->username} for {$validatedData['renewal_days']} days",
        ]);

        OrderRenewed::dispatch($order, $validatedData['renewal_days']);

        return $order;
    }

    public function inviteMemberAsClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'inviter_user_id' => ['required', 'exists:users,id'],
            'email' => ['required', 'email'],
        ])->validate();

        $order = Order::find($validatedData['order_id']);

        // ensure order is not terminated
        if ($order->isTerminated()) {
            throw ValidationException::withMessages([
                'order_id' => 'You cannot invite members to a terminated order.',
            ]);
        }

        // ensure order belongs to the user inviting the member
        if ($order->user_id !== $validatedData['inviter_user_id']) {
            throw ValidationException::withMessages([
                'order_id' => 'You can only invite members to your own orders.',
            ]);
        }

        // prevent user from inviting themselves
        if ($order->user->email === $validatedData['email']) {
            throw ValidationException::withMessages([
                'email' => 'You cannot invite yourself to an order.',
            ]);
        }

        // check if member with this email already exists
        $existingMember = $order->members()->where('email', $validatedData['email'])->first();

        if ($existingMember) {
            throw ValidationException::withMessages([
                'email' => 'Member with this email already exists in the order.',
            ]);
        }

        // check if there are no more than 5 members in the order
        if ($order->members()->count() >= 5) {
            throw ValidationException::withMessages([
                'email' => 'You can invite up to 5 members to an order.',
            ]);
        }

        // check if user with this email already exists
        $user = User::where('email', $validatedData['email'])->first();

        $member = $order->members()->create([
            'user_id' => $user ? $user->id : null,
            'email' => $validatedData['email'],
            'status' => 'pending',
        ]);

        $member->sendEmailNotification();

        return $member;
    }

    public function removeMemberAsClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'member_id' => ['required', 'exists:order_members,id'],
            'user_id' => ['required', 'exists:users,id'],
        ])->validate();

        $member = OrderMember::find($validatedData['member_id']);
        $user = User::find($validatedData['user_id']);

        if (! $member) {
            throw ValidationException::withMessages([
                'member_id' => 'Member not found',
            ]);
        }

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        // ensure member belongs to the user removing the member
        if ($member->order->user_id !== $validatedData['user_id']) {
            throw ValidationException::withMessages([
                'member_id' => 'You can only remove members from your own orders.',
            ]);
        }

        $member->delete();

        return true;
    }

    public function acceptInviteAsClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'member_id' => ['required', 'exists:order_members,id'],
            'user_id' => ['required', 'exists:users,id'],
        ])->validate();

        $member = OrderMember::find($validatedData['member_id']);
        $user = User::find($validatedData['user_id']);

        if (! $member) {
            throw ValidationException::withMessages([
                'member_id' => 'Member not found',
            ]);
        }

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        // ensure member belongs to the user accepting the invite
        if ($member->email !== $user->email) {
            throw ValidationException::withMessages([
                'member_id' => 'You can only accept invites for your own email.',
            ]);
        }

        // ensure member is pending
        if ($member->status !== 'pending') {
            throw ValidationException::withMessages([
                'member_id' => 'You can only accept pending invites.',
            ]);
        }

        $member->update([
            'status' => 'active',
            'user_id' => $validatedData['user_id'],
        ]);

        $member->sendAcceptionEmailNotification($user);

        return $member;
    }

    public function declineInviteAsClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'member_id' => ['required', 'exists:order_members,id'],
            'user_id' => ['required', 'exists:users,id'],
        ])->validate();

        $member = OrderMember::find($validatedData['member_id']);
        $user = User::find($validatedData['user_id']);

        if (! $member) {
            throw ValidationException::withMessages([
                'member_id' => 'Member not found',
            ]);
        }

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        // ensure member belongs to the user declining the invite
        if ($member->email !== $user->email) {
            throw ValidationException::withMessages([
                'member_id' => 'You can only decline invites for your own email.',
            ]);
        }

        $member->delete();

        return true;
    }

    public function changeServerPasswordAsClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ])->validate();

        $order = Order::find($validatedData['order_id']);
        $user = User::find($validatedData['user_id']);

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => 'Order not found',
            ]);
        }

        if (! $order->canChangeExternalPassword()) {
            throw ValidationException::withMessages([
                'new_password' => 'This order does not support password changes.',
            ]);
        }

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        // ensure order belongs to the user changing the password
        if ($order->user_id !== $validatedData['user_id']) {
            throw ValidationException::withMessages([
                'new_password' => 'You can only change the password for your own orders.',
            ]);
        }

        // ensure order is active
        if ($order->status !== 'active') {
            throw ValidationException::withMessages([
                'new_password' => 'You can only change the password for active orders.',
            ]);
        }

        try {
            $order->package->serverConnection->server->functions()->changePassword($order, $validatedData['new_password']);
        } catch (Exception $e) {
            throw ValidationException::withMessages([
                'new_password' => 'Failed to change password: '.$e->getMessage(),
            ]);
        }

        return true;
    }

    public static function createSubscriptionAsClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'gateway_config_id' => ['required', 'exists:gateway_configs,id'],
            'user_id' => ['required', 'exists:users,id'],
        ])->validate();

        $order = Order::find($validatedData['order_id']);
        $gatewayConfig = GatewayConfig::find($validatedData['gateway_config_id']);

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => 'Order not found',
            ]);
        }

        if (! $gatewayConfig) {
            throw ValidationException::withMessages([
                'gateway_id' => 'Gateway configuration not found',
            ]);
        }

        if (! $order->isRecurring()) {
            throw ValidationException::withMessages([
                'order_id' => 'This order is not eligible for subscriptions.',
            ]);
        }

        if (false) {
            throw ValidationException::withMessages([
                'order_id' => 'This order already has an active subscription.',
            ]);
        }

        $subscription = Subscription::create([
            'user_id' => $validatedData['user_id'],
            'gateway_config_id' => $gatewayConfig->id,
            'subscribable_type' => Order::class,
            'subscribable_id' => $order->id,
            'status' => 'pending',
            'description' => "Subscription for Order #{$order->id}",
            'currency' => settings('currency', 'USD'),
            'amount' => $order->price,
            'frequency' => $order->period_in_days,
            'success_url' => route('orders.view.subscription', $order->id),
            'cancel_url' => route('orders.view.subscription', $order->id),
            'handler' => OrderSubscriptionHandler::class,
        ]);

        return $subscription;
    }
}
