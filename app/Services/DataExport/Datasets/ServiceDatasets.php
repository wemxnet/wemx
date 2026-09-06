<?php

namespace App\Services\DataExport\Datasets;

use App\Services\DataExport\DataExportDatasetProvider;
use App\Services\DataExport\DataExportDefinition;
use App\Services\DataExport\DataExportValue;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ServiceDatasets implements DataExportDatasetProvider
{
    public const GROUP = 'Orders & subscriptions';

    public function definitions(): array
    {
        return [
            $this->orders(),
            $this->upcomingRenewals(),
            $this->cancellations(),
            $this->subscriptions(),
            $this->purchasedLineItems(),
            $this->orderAddons(),
        ];
    }

    protected function orders(): DataExportDefinition
    {
        return DataExportDefinition::make('orders', 'Orders & services', self::GROUP)
            ->describedAs('Every customer service with its package, billing cycle, recurring price and renewal date.')
            ->withIcon('archive')
            ->requiring('admin.orders')
            ->filteredByDate('orders.created_at', 'Order date')
            ->withColumns([
                'order_id' => 'Order ID',
                'external_id' => 'External ID',
                'status' => 'Status',
                'customer_id' => 'Customer ID',
                'customer' => 'Customer',
                'customer_email' => 'Customer email',
                'category' => 'Category',
                'package' => 'Package',
                'price_label' => 'Price label',
                'billing_cycle' => 'Billing cycle',
                'billing_cycle_days' => 'Billing cycle (days)',
                'currency' => 'Currency',
                'cycle_price' => 'Recurring price',
                'monthly_value' => 'Monthly value',
                'setup_fee' => 'Setup fee',
                'upgrade_fee' => 'Upgrade fee',
                'auto_renew_from_credit' => 'Auto renew from credit',
                'due_date' => 'Next due date',
                'last_renewed_at' => 'Last renewed at',
                'created_at' => 'Created at',
            ])
            ->sourcedFrom(
                fn () => $this->orderQuery()->orderByDesc('orders.id'),
                fn (object $row): array => [
                    'order_id' => DataExportValue::text($row->order_id),
                    'external_id' => DataExportValue::text($row->external_id),
                    'status' => DataExportValue::text($row->status),
                    'customer_id' => DataExportValue::text($row->customer_id),
                    'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                    'customer_email' => DataExportValue::text($row->customer_email),
                    'category' => DataExportValue::text($row->category),
                    'package' => DataExportValue::text($row->package),
                    'price_label' => DataExportValue::text($row->price_label),
                    'billing_cycle' => DataExportValue::billingCycle($row->period_in_days),
                    'billing_cycle_days' => DataExportValue::text($row->period_in_days),
                    'currency' => baseCurrency(),
                    'cycle_price' => DataExportValue::amount($row->cycle_price),
                    'monthly_value' => DataExportValue::monthlyValue($row->cycle_price, $row->period_in_days),
                    'setup_fee' => DataExportValue::amount($row->setup_fee),
                    'upgrade_fee' => DataExportValue::amount($row->upgrade_fee),
                    'auto_renew_from_credit' => DataExportValue::yesNo($row->auto_balance_renew),
                    'due_date' => DataExportValue::timestamp($row->due_date),
                    'last_renewed_at' => DataExportValue::timestamp($row->last_renewed_at),
                    'created_at' => DataExportValue::timestamp($row->created_at),
                ],
            );
    }

    protected function upcomingRenewals(): DataExportDefinition
    {
        return DataExportDefinition::make('upcoming_renewals', 'Renewal forecast', self::GROUP)
            ->describedAs('Services due for renewal, with the amount expected and whether the customer has enough credit to cover an automatic renewal.')
            ->withIcon('calendar-dollar')
            ->requiring('admin.orders')
            ->filteredByDate('orders.due_date', 'Due date')
            ->withColumns([
                'due_date' => 'Due date',
                'days_until_due' => 'Days until due',
                'order_id' => 'Order ID',
                'status' => 'Status',
                'customer' => 'Customer',
                'customer_email' => 'Customer email',
                'package' => 'Package',
                'billing_cycle' => 'Billing cycle',
                'currency' => 'Currency',
                'renewal_amount' => 'Renewal amount',
                'auto_renew_from_credit' => 'Auto renew from credit',
                'customer_credit' => 'Customer credit',
                'credit_covers_renewal' => 'Credit covers renewal',
            ])
            ->sourcedFrom(
                fn () => $this->orderQuery()
                    ->whereIn('orders.status', ['active', 'suspended'])
                    ->whereNotNull('orders.due_date')
                    ->orderBy('orders.due_date'),
                fn (object $row): array => [
                    'due_date' => DataExportValue::timestamp($row->due_date),
                    'days_until_due' => DataExportValue::daysBetween(now(), $row->due_date),
                    'order_id' => DataExportValue::text($row->order_id),
                    'status' => DataExportValue::text($row->status),
                    'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                    'customer_email' => DataExportValue::text($row->customer_email),
                    'package' => DataExportValue::text($row->package),
                    'billing_cycle' => DataExportValue::billingCycle($row->period_in_days),
                    'currency' => baseCurrency(),
                    'renewal_amount' => DataExportValue::amount($row->cycle_price),
                    'auto_renew_from_credit' => DataExportValue::yesNo($row->auto_balance_renew),
                    'customer_credit' => DataExportValue::amount($row->customer_balance),
                    'credit_covers_renewal' => DataExportValue::yesNo(
                        (float) $row->customer_balance >= (float) $row->cycle_price,
                    ),
                ],
            );
    }

    protected function cancellations(): DataExportDefinition
    {
        return DataExportDefinition::make('cancellations', 'Churn & cancellations', self::GROUP)
            ->describedAs('Suspended and terminated services with how long they stayed active and the recurring revenue lost.')
            ->withIcon('trending-down')
            ->requiring('admin.orders')
            ->filteredByDate('orders.updated_at', 'Status change date')
            ->withColumns([
                'changed_at' => 'Status changed at',
                'status' => 'Status',
                'order_id' => 'Order ID',
                'customer' => 'Customer',
                'customer_email' => 'Customer email',
                'category' => 'Category',
                'package' => 'Package',
                'billing_cycle' => 'Billing cycle',
                'currency' => 'Currency',
                'cycle_price' => 'Recurring price lost',
                'monthly_value' => 'Monthly value lost',
                'days_active' => 'Days active',
                'created_at' => 'Ordered at',
            ])
            ->sourcedFrom(
                fn () => $this->orderQuery()
                    ->whereIn('orders.status', ['suspended', 'terminated'])
                    ->orderByDesc('orders.updated_at'),
                fn (object $row): array => [
                    'changed_at' => DataExportValue::timestamp($row->updated_at),
                    'status' => DataExportValue::text($row->status),
                    'order_id' => DataExportValue::text($row->order_id),
                    'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                    'customer_email' => DataExportValue::text($row->customer_email),
                    'category' => DataExportValue::text($row->category),
                    'package' => DataExportValue::text($row->package),
                    'billing_cycle' => DataExportValue::billingCycle($row->period_in_days),
                    'currency' => baseCurrency(),
                    'cycle_price' => DataExportValue::amount($row->cycle_price),
                    'monthly_value' => DataExportValue::monthlyValue($row->cycle_price, $row->period_in_days),
                    'days_active' => DataExportValue::daysBetween($row->created_at, $row->updated_at),
                    'created_at' => DataExportValue::timestamp($row->created_at),
                ],
            );
    }

    protected function subscriptions(): DataExportDefinition
    {
        return DataExportDefinition::make('subscriptions', 'Gateway subscriptions', self::GROUP)
            ->describedAs('Recurring billing agreements held at the payment gateway, normalised to a monthly value so you can total up MRR.')
            ->withIcon('refresh')
            ->requiring('admin.subscriptions')
            ->filteredByDate('subscriptions.created_at', 'Created date')
            ->withColumns([
                'subscription_id' => 'Subscription ID',
                'gateway_reference' => 'Gateway reference',
                'status' => 'Status',
                'customer_id' => 'Customer ID',
                'customer' => 'Customer',
                'customer_email' => 'Customer email',
                'description' => 'Description',
                'gateway' => 'Gateway',
                'currency' => 'Currency',
                'amount' => 'Amount per cycle',
                'billing_cycle' => 'Billing cycle',
                'monthly_value' => 'Monthly value',
                'activated_at' => 'Activated at',
                'next_billing_at' => 'Next billing at',
                'cancelled_at' => 'Cancelled at',
                'cancel_reason' => 'Cancellation reason',
                'created_at' => 'Created at',
            ])
            ->sourcedFrom(
                fn () => DB::table('subscriptions')
                    ->leftJoin('users', 'users.id', '=', 'subscriptions.user_id')
                    ->leftJoin('gateway_configs', 'gateway_configs.id', '=', 'subscriptions.gateway_config_id')
                    ->orderByDesc('subscriptions.id')
                    ->select([
                        'subscriptions.id',
                        'subscriptions.subscription_id as gateway_reference',
                        'subscriptions.status',
                        'subscriptions.description',
                        'subscriptions.currency',
                        'subscriptions.amount',
                        'subscriptions.frequency',
                        'subscriptions.activated_at',
                        'subscriptions.next_billing_at',
                        'subscriptions.cancelled_at',
                        'subscriptions.cancel_reason',
                        'subscriptions.created_at',
                        'subscriptions.user_id as customer_id',
                        'users.email as customer_email',
                        'users.first_name',
                        'users.last_name',
                        'gateway_configs.display_name as gateway',
                    ]),
                fn (object $row): array => [
                    'subscription_id' => DataExportValue::text($row->id),
                    'gateway_reference' => DataExportValue::text($row->gateway_reference),
                    'status' => DataExportValue::text($row->status),
                    'customer_id' => DataExportValue::text($row->customer_id),
                    'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                    'customer_email' => DataExportValue::text($row->customer_email),
                    'description' => DataExportValue::text($row->description),
                    'gateway' => DataExportValue::text($row->gateway),
                    'currency' => DataExportValue::text($row->currency),
                    'amount' => DataExportValue::amount($row->amount),
                    'billing_cycle' => DataExportValue::billingCycle($row->frequency),
                    'monthly_value' => DataExportValue::monthlyValue($row->amount, $row->frequency),
                    'activated_at' => DataExportValue::timestamp($row->activated_at),
                    'next_billing_at' => DataExportValue::timestamp($row->next_billing_at),
                    'cancelled_at' => DataExportValue::timestamp($row->cancelled_at),
                    'cancel_reason' => DataExportValue::text($row->cancel_reason),
                    'created_at' => DataExportValue::timestamp($row->created_at),
                ],
            );
    }

    protected function purchasedLineItems(): DataExportDefinition
    {
        return DataExportDefinition::make('purchased_line_items', 'Checkout line items', self::GROUP)
            ->describedAs('Frozen basket lines captured at checkout — what was actually bought, at what price and quantity.')
            ->withIcon('shopping-cart')
            ->requiring('admin.orders')
            ->filteredByDate('cart_order_items.created_at', 'Checkout date')
            ->withColumns([
                'purchased_at' => 'Purchased at',
                'basket_identifier' => 'Basket',
                'customer_id' => 'Customer ID',
                'customer' => 'Customer',
                'customer_email' => 'Customer email',
                'item' => 'Item',
                'currency' => 'Currency',
                'unit_price' => 'Unit price',
                'quantity' => 'Quantity',
                'line_total' => 'Line total',
                'is_paid' => 'Paid',
                'item_type' => 'Item type',
                'item_id' => 'Item ID',
                'handler' => 'Handler',
            ])
            ->sourcedFrom(
                fn () => DB::table('cart_order_items')
                    ->leftJoin('users', 'users.id', '=', 'cart_order_items.user_id')
                    ->orderByDesc('cart_order_items.id')
                    ->select([
                        'cart_order_items.created_at',
                        'cart_order_items.basket_identifier',
                        'cart_order_items.name',
                        'cart_order_items.price',
                        'cart_order_items.quantity',
                        'cart_order_items.is_paid',
                        'cart_order_items.cartable_type',
                        'cart_order_items.cartable_id',
                        'cart_order_items.handler',
                        'cart_order_items.user_id as customer_id',
                        'users.email as customer_email',
                        'users.first_name',
                        'users.last_name',
                    ]),
                fn (object $row): array => [
                    'purchased_at' => DataExportValue::timestamp($row->created_at),
                    'basket_identifier' => DataExportValue::text($row->basket_identifier),
                    'customer_id' => DataExportValue::text($row->customer_id),
                    'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                    'customer_email' => DataExportValue::text($row->customer_email),
                    'item' => DataExportValue::text($row->name),
                    'currency' => baseCurrency(),
                    'unit_price' => DataExportValue::amount($row->price),
                    'quantity' => DataExportValue::text($row->quantity),
                    'line_total' => DataExportValue::amount((float) $row->price * (int) $row->quantity),
                    'is_paid' => DataExportValue::yesNo($row->is_paid),
                    'item_type' => DataExportValue::className($row->cartable_type),
                    'item_id' => DataExportValue::text($row->cartable_id),
                    'handler' => DataExportValue::className($row->handler),
                ],
            );
    }

    protected function orderAddons(): DataExportDefinition
    {
        return DataExportDefinition::make('order_addons', 'Order add-ons & config options', self::GROUP)
            ->describedAs('Configurable options and extra charges attached to individual orders, on top of the base package price.')
            ->withIcon('adjustments')
            ->requiring('admin.orders')
            ->filteredByDate('order_prices.created_at', 'Added date')
            ->withColumns([
                'added_at' => 'Added at',
                'order_id' => 'Order ID',
                'order_status' => 'Order status',
                'customer' => 'Customer',
                'customer_email' => 'Customer email',
                'package' => 'Package',
                'type' => 'Type',
                'option_key' => 'Option key',
                'option_value' => 'Option value',
                'description' => 'Description',
                'currency' => 'Currency',
                'cycle_price' => 'Recurring price',
                'upgrade_fee' => 'Upgrade fee',
                'is_active' => 'Active',
            ])
            ->sourcedFrom(
                fn () => DB::table('order_prices')
                    ->leftJoin('orders', 'orders.id', '=', 'order_prices.order_id')
                    ->leftJoin('users', 'users.id', '=', 'orders.user_id')
                    ->leftJoin('packages', 'packages.id', '=', 'orders.package_id')
                    ->orderByDesc('order_prices.id')
                    ->select([
                        'order_prices.created_at',
                        'order_prices.type',
                        'order_prices.key',
                        'order_prices.value',
                        'order_prices.description',
                        'order_prices.cycle_price',
                        'order_prices.upgrade_fee',
                        'order_prices.is_active',
                        'order_prices.order_id',
                        'orders.status as order_status',
                        'users.email as customer_email',
                        'users.first_name',
                        'users.last_name',
                        'packages.name as package',
                    ]),
                fn (object $row): array => [
                    'added_at' => DataExportValue::timestamp($row->created_at),
                    'order_id' => DataExportValue::text($row->order_id),
                    'order_status' => DataExportValue::text($row->order_status),
                    'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                    'customer_email' => DataExportValue::text($row->customer_email),
                    'package' => DataExportValue::text($row->package),
                    'type' => DataExportValue::text($row->type),
                    'option_key' => DataExportValue::text($row->key),
                    'option_value' => DataExportValue::text($row->value),
                    'description' => DataExportValue::text($row->description),
                    'currency' => baseCurrency(),
                    'cycle_price' => DataExportValue::amount($row->cycle_price),
                    'upgrade_fee' => DataExportValue::amount($row->upgrade_fee),
                    'is_active' => DataExportValue::yesNo($row->is_active),
                ],
            );
    }

    /**
     * Shared order projection used by the orders, renewals and churn exports.
     */
    protected function orderQuery(): Builder
    {
        return DB::table('orders')
            ->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->leftJoin('packages', 'packages.id', '=', 'orders.package_id')
            ->leftJoin('categories', 'categories.id', '=', 'packages.category_id')
            ->leftJoin('package_prices', 'package_prices.id', '=', 'orders.package_price_id')
            ->select([
                'orders.id as order_id',
                'orders.external_id',
                'orders.status',
                'orders.cycle_price',
                'orders.setup_fee',
                'orders.upgrade_fee',
                'orders.period_in_days',
                'orders.due_date',
                'orders.last_renewed_at',
                'orders.auto_balance_renew',
                'orders.created_at',
                'orders.updated_at',
                'orders.user_id as customer_id',
                'users.email as customer_email',
                'users.first_name',
                'users.last_name',
                'users.balance as customer_balance',
                'packages.name as package',
                'categories.name as category',
                'package_prices.short_description as price_label',
            ]);
    }
}
