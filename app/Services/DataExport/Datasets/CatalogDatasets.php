<?php

namespace App\Services\DataExport\Datasets;

use App\Services\DataExport\DataExportDatasetProvider;
use App\Services\DataExport\DataExportDefinition;
use App\Services\DataExport\DataExportValue;
use Illuminate\Support\Facades\DB;

class CatalogDatasets implements DataExportDatasetProvider
{
    public const GROUP = 'Catalog & pricing';

    public function definitions(): array
    {
        return [
            $this->packages(),
            $this->packagePrices(),
        ];
    }

    protected function packages(): DataExportDefinition
    {
        return DataExportDefinition::make('packages', 'Packages', self::GROUP)
            ->describedAs('The full product catalog with how many services are currently sold on each package.')
            ->withIcon('package')
            ->requiring('admin.packages.index')
            ->filteredByDate('packages.created_at', 'Created date')
            ->withColumns([
                'package_id' => 'Package ID',
                'name' => 'Name',
                'slug' => 'Slug',
                'category' => 'Category',
                'status' => 'Status',
                'server_connection' => 'Server connection',
                'active_prices' => 'Active prices',
                'active_orders' => 'Active orders',
                'total_orders' => 'Total orders',
                'global_quantity' => 'Global stock limit',
                'client_quantity' => 'Per-customer limit',
                'sort_order' => 'Sort order',
                'created_at' => 'Created at',
            ])
            ->sourcedFrom(
                fn () => DB::table('packages')
                    ->leftJoin('categories', 'categories.id', '=', 'packages.category_id')
                    ->leftJoin('server_connections', 'server_connections.id', '=', 'packages.connection_id')
                    ->orderBy('packages.sort_order')
                    ->orderBy('packages.name')
                    ->select([
                        'packages.id as package_id',
                        'packages.name',
                        'packages.slug',
                        'packages.status',
                        'packages.global_quantity',
                        'packages.client_quantity',
                        'packages.sort_order',
                        'packages.created_at',
                        'categories.name as category',
                        'server_connections.alias as server_connection',
                    ])
                    ->selectSub(
                        DB::table('package_prices')
                            ->selectRaw('count(*)')
                            ->whereColumn('package_prices.package_id', 'packages.id')
                            ->where('package_prices.is_active', true),
                        'active_prices',
                    )
                    ->selectSub(
                        DB::table('orders')
                            ->selectRaw('count(*)')
                            ->whereColumn('orders.package_id', 'packages.id')
                            ->where('orders.status', 'active'),
                        'active_orders',
                    )
                    ->selectSub(
                        DB::table('orders')
                            ->selectRaw('count(*)')
                            ->whereColumn('orders.package_id', 'packages.id'),
                        'total_orders',
                    ),
                fn (object $row): array => [
                    'package_id' => DataExportValue::text($row->package_id),
                    'name' => DataExportValue::text($row->name),
                    'slug' => DataExportValue::text($row->slug),
                    'category' => DataExportValue::text($row->category),
                    'status' => DataExportValue::text($row->status),
                    'server_connection' => DataExportValue::text($row->server_connection),
                    'active_prices' => DataExportValue::text($row->active_prices),
                    'active_orders' => DataExportValue::text($row->active_orders),
                    'total_orders' => DataExportValue::text($row->total_orders),
                    'global_quantity' => DataExportValue::text($row->global_quantity),
                    'client_quantity' => DataExportValue::text($row->client_quantity),
                    'sort_order' => DataExportValue::text($row->sort_order),
                    'created_at' => DataExportValue::timestamp($row->created_at),
                ],
            );
    }

    protected function packagePrices(): DataExportDefinition
    {
        return DataExportDefinition::make('package_prices', 'Price list', self::GROUP)
            ->describedAs('Every billing cycle you sell, with its price, setup fee and how many active services sit on it. Useful before a price change.')
            ->withIcon('tag')
            ->requiring('admin.packages.index')
            ->filteredByDate('package_prices.created_at', 'Created date')
            ->withColumns([
                'price_id' => 'Price ID',
                'package' => 'Package',
                'category' => 'Category',
                'label' => 'Label',
                'billing_cycle' => 'Billing cycle',
                'billing_cycle_days' => 'Billing cycle (days)',
                'currency' => 'Currency',
                'price' => 'Price',
                'monthly_value' => 'Monthly value',
                'setup_fee' => 'Setup fee',
                'upgrade_fee' => 'Upgrade fee',
                'is_active' => 'Active',
                'active_orders' => 'Active orders',
            ])
            ->sourcedFrom(
                fn () => DB::table('package_prices')
                    ->leftJoin('packages', 'packages.id', '=', 'package_prices.package_id')
                    ->leftJoin('categories', 'categories.id', '=', 'packages.category_id')
                    ->orderBy('packages.name')
                    ->orderBy('package_prices.period_in_days')
                    ->select([
                        'package_prices.id as price_id',
                        'package_prices.short_description as label',
                        'package_prices.period_in_days',
                        'package_prices.price',
                        'package_prices.setup_fee',
                        'package_prices.upgrade_fee',
                        'package_prices.is_active',
                        'packages.name as package',
                        'categories.name as category',
                    ])
                    ->selectSub(
                        DB::table('orders')
                            ->selectRaw('count(*)')
                            ->whereColumn('orders.package_price_id', 'package_prices.id')
                            ->where('orders.status', 'active'),
                        'active_orders',
                    ),
                fn (object $row): array => [
                    'price_id' => DataExportValue::text($row->price_id),
                    'package' => DataExportValue::text($row->package),
                    'category' => DataExportValue::text($row->category),
                    'label' => DataExportValue::text($row->label),
                    'billing_cycle' => DataExportValue::billingCycle($row->period_in_days),
                    'billing_cycle_days' => DataExportValue::text($row->period_in_days),
                    'currency' => baseCurrency(),
                    'price' => DataExportValue::amount($row->price),
                    'monthly_value' => DataExportValue::monthlyValue($row->price, $row->period_in_days),
                    'setup_fee' => DataExportValue::amount($row->setup_fee),
                    'upgrade_fee' => DataExportValue::amount($row->upgrade_fee),
                    'is_active' => DataExportValue::yesNo($row->is_active),
                    'active_orders' => DataExportValue::text($row->active_orders),
                ],
            );
    }
}
