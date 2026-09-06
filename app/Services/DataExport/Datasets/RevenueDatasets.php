<?php

namespace App\Services\DataExport\Datasets;

use App\Models\Order;
use App\Services\DataExport\DataExportDatasetProvider;
use App\Services\DataExport\DataExportDefinition;
use App\Services\DataExport\DataExportGrammar;
use App\Services\DataExport\DataExportValue;
use Illuminate\Support\Facades\DB;

class RevenueDatasets implements DataExportDatasetProvider
{
    public const GROUP = 'Revenue & billing';

    public function definitions(): array
    {
        return [
            $this->invoices(),
            $this->revenueByMonth(),
            $this->revenueByPackage(),
            $this->revenueByGateway(),
            $this->refunds(),
            $this->taxReport(),
            $this->accountsReceivable(),
            $this->creditLedger(),
        ];
    }

    protected function invoices(): DataExportDefinition
    {
        return DataExportDefinition::make('invoices', 'Invoices & payments', self::GROUP)
            ->describedAs('Every invoice raised, with subtotal, discount, tax, total and net earnings. The starting point for bookkeeping and reconciliation.')
            ->withIcon('file-invoice')
            ->requiring('admin.payments')
            ->filteredByDate('payments.created_at', 'Invoice date')
            ->withColumns([
                'invoice_id' => 'Invoice ID',
                'status' => 'Status',
                'invoice_date' => 'Invoice date',
                'paid_at' => 'Paid at',
                'customer_id' => 'Customer ID',
                'customer' => 'Customer',
                'customer_email' => 'Customer email',
                'description' => 'Description',
                'currency' => 'Currency',
                'subtotal' => 'Subtotal',
                'discount' => 'Discount',
                'tax' => 'Tax',
                'total' => 'Total',
                'net_earnings' => 'Net earnings',
                'gateway' => 'Gateway',
                'gateway_type' => 'Gateway type',
                'transaction_id' => 'Transaction ID',
                'billed_item' => 'Billed item',
                'billed_item_id' => 'Billed item ID',
            ])
            ->sourcedFrom(
                fn () => DB::table('payments')
                    ->leftJoin('users', 'users.id', '=', 'payments.user_id')
                    ->leftJoin('gateway_configs', 'gateway_configs.id', '=', 'payments.gateway_config_id')
                    ->orderByDesc('payments.id')
                    ->select([
                        'payments.invoice_id',
                        'payments.status',
                        'payments.description',
                        'payments.currency',
                        'payments.subtotal',
                        'payments.discount',
                        'payments.tax',
                        'payments.total',
                        'payments.earnings',
                        'payments.transaction_id',
                        'payments.payable_type',
                        'payments.payable_id',
                        'payments.created_at',
                        'payments.paid_at',
                        'payments.user_id as customer_id',
                        'users.email as customer_email',
                        'users.first_name',
                        'users.last_name',
                        'gateway_configs.display_name as gateway',
                        'gateway_configs.extension_identifier as gateway_type',
                    ]),
                fn (object $row): array => [
                    'invoice_id' => DataExportValue::text($row->invoice_id),
                    'status' => DataExportValue::text($row->status),
                    'invoice_date' => DataExportValue::timestamp($row->created_at),
                    'paid_at' => DataExportValue::timestamp($row->paid_at),
                    'customer_id' => DataExportValue::text($row->customer_id),
                    'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                    'customer_email' => DataExportValue::text($row->customer_email),
                    'description' => DataExportValue::text($row->description),
                    'currency' => DataExportValue::text($row->currency),
                    'subtotal' => DataExportValue::amount($row->subtotal),
                    'discount' => DataExportValue::amount($row->discount),
                    'tax' => DataExportValue::amount($row->tax),
                    'total' => DataExportValue::amount($row->total),
                    'net_earnings' => DataExportValue::amount($row->earnings),
                    'gateway' => DataExportValue::text($row->gateway),
                    'gateway_type' => DataExportValue::text($row->gateway_type),
                    'transaction_id' => DataExportValue::text($row->transaction_id),
                    'billed_item' => DataExportValue::className($row->payable_type),
                    'billed_item_id' => DataExportValue::text($row->payable_id),
                ],
            );
    }

    protected function revenueByMonth(): DataExportDefinition
    {
        $month = DataExportGrammar::yearMonth('payments.paid_at');

        return DataExportDefinition::make('revenue_by_month', 'Revenue by month', self::GROUP)
            ->describedAs('Monthly totals for paid invoices, split per currency. The summary most accountants and investors ask for.')
            ->withIcon('chart-histogram')
            ->requiring('admin.payments')
            ->filteredByDate('payments.paid_at', 'Paid date')
            ->aggregated()
            ->withColumns([
                'month' => 'Month',
                'currency' => 'Currency',
                'invoices' => 'Invoices',
                'subtotal' => 'Subtotal',
                'discount' => 'Discount',
                'tax' => 'Tax',
                'gross_total' => 'Gross total',
                'net_earnings' => 'Net earnings',
            ])
            ->sourcedFrom(
                fn () => DB::table('payments')
                    ->where('payments.status', 'paid')
                    ->whereNotNull('payments.paid_at')
                    ->selectRaw("{$month} as month")
                    ->selectRaw('payments.currency as currency')
                    ->selectRaw('count(*) as invoices')
                    ->selectRaw('sum(payments.subtotal) as subtotal')
                    ->selectRaw('sum(payments.discount) as discount')
                    ->selectRaw('sum(payments.tax) as tax')
                    ->selectRaw('sum(payments.total) as gross_total')
                    ->selectRaw('sum(payments.earnings) as net_earnings')
                    ->groupByRaw("{$month}, payments.currency")
                    ->orderByRaw("{$month} desc, payments.currency asc"),
                fn (object $row): array => [
                    'month' => DataExportValue::text($row->month),
                    'currency' => DataExportValue::text($row->currency),
                    'invoices' => DataExportValue::text($row->invoices),
                    'subtotal' => DataExportValue::amount($row->subtotal),
                    'discount' => DataExportValue::amount($row->discount),
                    'tax' => DataExportValue::amount($row->tax),
                    'gross_total' => DataExportValue::amount($row->gross_total),
                    'net_earnings' => DataExportValue::amount($row->net_earnings),
                ],
            );
    }

    protected function revenueByPackage(): DataExportDefinition
    {
        return DataExportDefinition::make('revenue_by_package', 'Revenue by package', self::GROUP)
            ->describedAs('Which packages actually earn money. Paid invoices attributed to the package behind the order.')
            ->withIcon('package')
            ->requiring('admin.payments')
            ->filteredByDate('payments.paid_at', 'Paid date')
            ->aggregated()
            ->withColumns([
                'package_id' => 'Package ID',
                'package' => 'Package',
                'category' => 'Category',
                'currency' => 'Currency',
                'invoices' => 'Invoices',
                'gross_total' => 'Gross total',
                'net_earnings' => 'Net earnings',
            ])
            ->sourcedFrom(
                fn () => DB::table('payments')
                    ->join('orders', function ($join) {
                        $join->on('orders.id', '=', 'payments.payable_id')
                            ->where('payments.payable_type', Order::class);
                    })
                    ->join('packages', 'packages.id', '=', 'orders.package_id')
                    ->leftJoin('categories', 'categories.id', '=', 'packages.category_id')
                    ->where('payments.status', 'paid')
                    ->whereNotNull('payments.paid_at')
                    ->selectRaw('packages.id as package_id')
                    ->selectRaw('packages.name as package')
                    ->selectRaw('categories.name as category')
                    ->selectRaw('payments.currency as currency')
                    ->selectRaw('count(*) as invoices')
                    ->selectRaw('sum(payments.total) as gross_total')
                    ->selectRaw('sum(payments.earnings) as net_earnings')
                    ->groupBy('packages.id', 'packages.name', 'categories.name', 'payments.currency')
                    ->orderByRaw('sum(payments.total) desc'),
                fn (object $row): array => [
                    'package_id' => DataExportValue::text($row->package_id),
                    'package' => DataExportValue::text($row->package),
                    'category' => DataExportValue::text($row->category),
                    'currency' => DataExportValue::text($row->currency),
                    'invoices' => DataExportValue::text($row->invoices),
                    'gross_total' => DataExportValue::amount($row->gross_total),
                    'net_earnings' => DataExportValue::amount($row->net_earnings),
                ],
            );
    }

    protected function revenueByGateway(): DataExportDefinition
    {
        return DataExportDefinition::make('revenue_by_gateway', 'Revenue by gateway', self::GROUP)
            ->describedAs('Volume per payment method, including the gap between what customers paid and what landed in your account.')
            ->withIcon('cash-register')
            ->requiring('admin.payments')
            ->filteredByDate('payments.paid_at', 'Paid date')
            ->aggregated()
            ->withColumns([
                'gateway' => 'Gateway',
                'gateway_type' => 'Gateway type',
                'currency' => 'Currency',
                'invoices' => 'Invoices',
                'gross_total' => 'Gross total',
                'net_earnings' => 'Net earnings',
                'processing_costs' => 'Processing costs',
            ])
            ->sourcedFrom(
                fn () => DB::table('payments')
                    ->leftJoin('gateway_configs', 'gateway_configs.id', '=', 'payments.gateway_config_id')
                    ->where('payments.status', 'paid')
                    ->whereNotNull('payments.paid_at')
                    ->selectRaw('gateway_configs.display_name as gateway')
                    ->selectRaw('gateway_configs.extension_identifier as gateway_type')
                    ->selectRaw('payments.currency as currency')
                    ->selectRaw('count(*) as invoices')
                    ->selectRaw('sum(payments.total) as gross_total')
                    ->selectRaw('sum(payments.earnings) as net_earnings')
                    ->selectRaw('sum(payments.total - payments.earnings) as processing_costs')
                    ->groupBy('gateway_configs.display_name', 'gateway_configs.extension_identifier', 'payments.currency')
                    ->orderByRaw('sum(payments.total) desc'),
                fn (object $row): array => [
                    'gateway' => DataExportValue::text($row->gateway),
                    'gateway_type' => DataExportValue::text($row->gateway_type),
                    'currency' => DataExportValue::text($row->currency),
                    'invoices' => DataExportValue::text($row->invoices),
                    'gross_total' => DataExportValue::amount($row->gross_total),
                    'net_earnings' => DataExportValue::amount($row->net_earnings),
                    'processing_costs' => DataExportValue::amount($row->processing_costs),
                ],
            );
    }

    protected function refunds(): DataExportDefinition
    {
        return DataExportDefinition::make('refunds', 'Refunds', self::GROUP)
            ->describedAs('Every refund issued with its reason and the invoice it came from, so revenue can be adjusted correctly.')
            ->withIcon('receipt-refund')
            ->requiring('admin.payments')
            ->filteredByDate('payment_refunds.created_at', 'Refund date')
            ->withColumns([
                'refunded_at' => 'Refunded at',
                'invoice_id' => 'Invoice ID',
                'customer' => 'Customer',
                'customer_email' => 'Customer email',
                'description' => 'Invoice description',
                'currency' => 'Currency',
                'refund_amount' => 'Refund amount',
                'invoice_total' => 'Original invoice total',
                'reason' => 'Reason',
                'transaction_id' => 'Transaction ID',
                'gateway' => 'Gateway',
            ])
            ->sourcedFrom(
                fn () => DB::table('payment_refunds')
                    ->leftJoin('payments', 'payments.id', '=', 'payment_refunds.payment_id')
                    ->leftJoin('users', 'users.id', '=', 'payment_refunds.user_id')
                    ->leftJoin('gateway_configs', 'gateway_configs.id', '=', 'payment_refunds.gateway_config_id')
                    ->orderByDesc('payment_refunds.id')
                    ->select([
                        'payment_refunds.created_at',
                        'payment_refunds.amount',
                        'payment_refunds.currency',
                        'payment_refunds.reason',
                        'payment_refunds.transaction_id',
                        'payments.invoice_id',
                        'payments.description',
                        'payments.total as invoice_total',
                        'users.email as customer_email',
                        'users.first_name',
                        'users.last_name',
                        'gateway_configs.display_name as gateway',
                    ]),
                fn (object $row): array => [
                    'refunded_at' => DataExportValue::timestamp($row->created_at),
                    'invoice_id' => DataExportValue::text($row->invoice_id),
                    'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                    'customer_email' => DataExportValue::text($row->customer_email),
                    'description' => DataExportValue::text($row->description),
                    'currency' => DataExportValue::text($row->currency),
                    'refund_amount' => DataExportValue::amount($row->amount),
                    'invoice_total' => DataExportValue::amount($row->invoice_total),
                    'reason' => DataExportValue::text($row->reason),
                    'transaction_id' => DataExportValue::text($row->transaction_id),
                    'gateway' => DataExportValue::text($row->gateway),
                ],
            );
    }

    protected function taxReport(): DataExportDefinition
    {
        return DataExportDefinition::make('tax_report', 'Sales tax / VAT report', self::GROUP)
            ->describedAs('Tax collected per paid invoice with the billing jurisdiction, rate and VAT ID captured at checkout. Built for tax filings.')
            ->withIcon('receipt-tax')
            ->requiring('admin.payments')
            ->filteredByDate('payments.paid_at', 'Paid date')
            ->withColumns([
                'paid_at' => 'Paid at',
                'invoice_id' => 'Invoice ID',
                'customer' => 'Customer',
                'customer_email' => 'Customer email',
                'company_name' => 'Company name',
                'tax_id' => 'Tax / VAT ID',
                'country' => 'Country',
                'region' => 'Region',
                'city' => 'City',
                'zip_code' => 'Postal code',
                'tax_name' => 'Tax name',
                'tax_rate' => 'Tax rate (%)',
                'tax_exempt' => 'Tax exempt',
                'tax_exempt_reason' => 'Exemption reason',
                'currency' => 'Currency',
                'taxable_amount' => 'Taxable amount',
                'tax_amount' => 'Tax amount',
                'total' => 'Total',
            ])
            ->sourcedFrom(
                fn () => DB::table('payments')
                    ->leftJoin('payment_tax_details', 'payment_tax_details.payment_id', '=', 'payments.id')
                    ->leftJoin('users', 'users.id', '=', 'payments.user_id')
                    ->where('payments.status', 'paid')
                    ->whereNotNull('payments.paid_at')
                    ->orderByDesc('payments.paid_at')
                    ->select([
                        'payments.invoice_id',
                        'payments.currency',
                        'payments.subtotal',
                        'payments.discount',
                        'payments.tax',
                        'payments.total',
                        'payments.paid_at',
                        'users.email as customer_email',
                        'users.first_name',
                        'users.last_name',
                        'payment_tax_details.company_name',
                        'payment_tax_details.tax_id',
                        'payment_tax_details.country',
                        'payment_tax_details.region',
                        'payment_tax_details.city',
                        'payment_tax_details.zip_code',
                        'payment_tax_details.tax_name',
                        'payment_tax_details.tax_rate',
                        'payment_tax_details.tax_exempt',
                        'payment_tax_details.tax_exempt_reason',
                    ]),
                fn (object $row): array => [
                    'paid_at' => DataExportValue::timestamp($row->paid_at),
                    'invoice_id' => DataExportValue::text($row->invoice_id),
                    'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                    'customer_email' => DataExportValue::text($row->customer_email),
                    'company_name' => DataExportValue::text($row->company_name),
                    'tax_id' => DataExportValue::text($row->tax_id),
                    'country' => DataExportValue::text($row->country),
                    'region' => DataExportValue::text($row->region),
                    'city' => DataExportValue::text($row->city),
                    'zip_code' => DataExportValue::text($row->zip_code),
                    'tax_name' => DataExportValue::text($row->tax_name),
                    'tax_rate' => DataExportValue::rate($row->tax_rate),
                    'tax_exempt' => DataExportValue::yesNo($row->tax_exempt),
                    'tax_exempt_reason' => DataExportValue::text($row->tax_exempt_reason),
                    'currency' => DataExportValue::text($row->currency),
                    'taxable_amount' => DataExportValue::amount((float) $row->subtotal - (float) $row->discount),
                    'tax_amount' => DataExportValue::amount($row->tax),
                    'total' => DataExportValue::amount($row->total),
                ],
            );
    }

    protected function accountsReceivable(): DataExportDefinition
    {
        return DataExportDefinition::make('accounts_receivable', 'Unpaid invoices (aged)', self::GROUP)
            ->describedAs('Outstanding invoices bucketed by how long they have been unpaid, for chasing overdue accounts.')
            ->withIcon('clock-dollar')
            ->requiring('admin.payments')
            ->filteredByDate('payments.created_at', 'Invoice date')
            ->withColumns([
                'invoice_id' => 'Invoice ID',
                'invoice_date' => 'Invoice date',
                'days_outstanding' => 'Days outstanding',
                'aging_bucket' => 'Aging bucket',
                'customer_id' => 'Customer ID',
                'customer' => 'Customer',
                'customer_email' => 'Customer email',
                'description' => 'Description',
                'currency' => 'Currency',
                'total' => 'Amount due',
                'customer_balance' => 'Customer credit balance',
                'gateway' => 'Gateway',
            ])
            ->sourcedFrom(
                fn () => DB::table('payments')
                    ->leftJoin('users', 'users.id', '=', 'payments.user_id')
                    ->leftJoin('gateway_configs', 'gateway_configs.id', '=', 'payments.gateway_config_id')
                    ->where('payments.status', 'unpaid')
                    ->orderBy('payments.created_at')
                    ->select([
                        'payments.invoice_id',
                        'payments.description',
                        'payments.currency',
                        'payments.total',
                        'payments.created_at',
                        'payments.user_id as customer_id',
                        'users.email as customer_email',
                        'users.first_name',
                        'users.last_name',
                        'users.balance as customer_balance',
                        'gateway_configs.display_name as gateway',
                    ]),
                function (object $row): array {
                    $daysOutstanding = DataExportValue::daysBetween($row->created_at, now());

                    return [
                        'invoice_id' => DataExportValue::text($row->invoice_id),
                        'invoice_date' => DataExportValue::timestamp($row->created_at),
                        'days_outstanding' => $daysOutstanding,
                        'aging_bucket' => DataExportValue::agingBucket($daysOutstanding),
                        'customer_id' => DataExportValue::text($row->customer_id),
                        'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                        'customer_email' => DataExportValue::text($row->customer_email),
                        'description' => DataExportValue::text($row->description),
                        'currency' => DataExportValue::text($row->currency),
                        'total' => DataExportValue::amount($row->total),
                        'customer_balance' => DataExportValue::amount($row->customer_balance),
                        'gateway' => DataExportValue::text($row->gateway),
                    ];
                },
            );
    }

    protected function creditLedger(): DataExportDefinition
    {
        return DataExportDefinition::make('credit_ledger', 'Account credit ledger', self::GROUP)
            ->describedAs('Every top-up, deduction and manual adjustment to customer account credit, with the running balance.')
            ->withIcon('wallet')
            ->requiring('admin.users')
            ->filteredByDate('balance_transactions.created_at', 'Transaction date')
            ->withColumns([
                'transacted_at' => 'Transacted at',
                'customer_id' => 'Customer ID',
                'customer' => 'Customer',
                'customer_email' => 'Customer email',
                'type' => 'Type',
                'description' => 'Description',
                'currency' => 'Currency',
                'amount' => 'Amount',
                'balance_before' => 'Balance before',
                'balance_after' => 'Balance after',
            ])
            ->sourcedFrom(
                fn () => DB::table('balance_transactions')
                    ->leftJoin('users', 'users.id', '=', 'balance_transactions.user_id')
                    ->orderByDesc('balance_transactions.id')
                    ->select([
                        'balance_transactions.created_at',
                        'balance_transactions.result',
                        'balance_transactions.description',
                        'balance_transactions.amount',
                        'balance_transactions.balance_before_transaction',
                        'balance_transactions.user_id as customer_id',
                        'users.email as customer_email',
                        'users.first_name',
                        'users.last_name',
                    ]),
                function (object $row): array {
                    $before = (float) $row->balance_before_transaction;
                    $amount = (float) $row->amount;

                    return [
                        'transacted_at' => DataExportValue::timestamp($row->created_at),
                        'customer_id' => DataExportValue::text($row->customer_id),
                        'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                        'customer_email' => DataExportValue::text($row->customer_email),
                        'type' => match ($row->result) {
                            '+' => 'Credit added',
                            '-' => 'Credit used',
                            default => 'Balance set',
                        },
                        'description' => DataExportValue::text($row->description),
                        'currency' => baseCurrency(),
                        'amount' => DataExportValue::amount($amount),
                        'balance_before' => DataExportValue::amount($before),
                        'balance_after' => DataExportValue::amount(match ($row->result) {
                            '+' => $before + $amount,
                            '-' => $before - $amount,
                            default => $amount,
                        }),
                    ];
                },
            );
    }
}
