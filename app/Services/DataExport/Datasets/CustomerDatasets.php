<?php

namespace App\Services\DataExport\Datasets;

use App\Services\DataExport\DataExportDatasetProvider;
use App\Services\DataExport\DataExportDefinition;
use App\Services\DataExport\DataExportGrammar;
use App\Services\DataExport\DataExportValue;
use Illuminate\Support\Facades\DB;

class CustomerDatasets implements DataExportDatasetProvider
{
    public const GROUP = 'Customers';

    public function definitions(): array
    {
        return [
            $this->customers(),
            $this->customerLifetimeValue(),
            $this->billingAddresses(),
            $this->signupsByMonth(),
            $this->bans(),
        ];
    }

    protected function customers(): DataExportDefinition
    {
        return DataExportDefinition::make('customers', 'Customer list', self::GROUP)
            ->describedAs('Every account with contact details, credit balance and order counts. Credentials and two-factor secrets are never exported.')
            ->withIcon('users')
            ->requiring('admin.users')
            ->filteredByDate('users.created_at', 'Signup date')
            ->withColumns([
                'customer_id' => 'Customer ID',
                'username' => 'Username',
                'email' => 'Email',
                'first_name' => 'First name',
                'last_name' => 'Last name',
                'status' => 'Status',
                'country' => 'Country',
                'phone' => 'Phone',
                'language' => 'Language',
                'currency' => 'Currency',
                'credit_balance' => 'Credit balance',
                'total_orders' => 'Total orders',
                'active_orders' => 'Active orders',
                'paid_invoices' => 'Paid invoices',
                'email_verified' => 'Email verified',
                'marketing_opt_in' => 'Marketing opt-in',
                'two_factor' => 'Two-factor enabled',
                'signed_up_at' => 'Signed up at',
                'last_login_at' => 'Last login at',
                'last_seen_at' => 'Last seen at',
            ])
            ->sourcedFrom(
                fn () => DB::table('users')
                    ->orderByDesc('users.id')
                    ->select([
                        'users.id as customer_id',
                        'users.username',
                        'users.email',
                        'users.first_name',
                        'users.last_name',
                        'users.status',
                        'users.country',
                        'users.phone',
                        'users.language',
                        'users.balance',
                        'users.is_subscribed',
                        'users.tfa_enabled',
                        'users.email_verified_at',
                        'users.created_at',
                        'users.last_login_at',
                        'users.last_seen_at',
                    ])
                    ->selectSub(
                        DB::table('orders')
                            ->selectRaw('count(*)')
                            ->whereColumn('orders.user_id', 'users.id'),
                        'total_orders',
                    )
                    ->selectSub(
                        DB::table('orders')
                            ->selectRaw('count(*)')
                            ->whereColumn('orders.user_id', 'users.id')
                            ->where('orders.status', 'active'),
                        'active_orders',
                    )
                    ->selectSub(
                        DB::table('payments')
                            ->selectRaw('count(*)')
                            ->whereColumn('payments.user_id', 'users.id')
                            ->where('payments.status', 'paid'),
                        'paid_invoices',
                    ),
                fn (object $row): array => [
                    'customer_id' => DataExportValue::text($row->customer_id),
                    'username' => DataExportValue::text($row->username),
                    'email' => DataExportValue::text($row->email),
                    'first_name' => DataExportValue::text($row->first_name),
                    'last_name' => DataExportValue::text($row->last_name),
                    'status' => DataExportValue::text($row->status),
                    'country' => DataExportValue::text($row->country),
                    'phone' => DataExportValue::text($row->phone),
                    'language' => DataExportValue::text($row->language),
                    'currency' => baseCurrency(),
                    'credit_balance' => DataExportValue::amount($row->balance),
                    'total_orders' => DataExportValue::text($row->total_orders),
                    'active_orders' => DataExportValue::text($row->active_orders),
                    'paid_invoices' => DataExportValue::text($row->paid_invoices),
                    'email_verified' => DataExportValue::yesNo($row->email_verified_at),
                    'marketing_opt_in' => DataExportValue::yesNo($row->is_subscribed),
                    'two_factor' => DataExportValue::yesNo($row->tfa_enabled),
                    'signed_up_at' => DataExportValue::timestamp($row->created_at),
                    'last_login_at' => DataExportValue::timestamp($row->last_login_at),
                    'last_seen_at' => DataExportValue::timestamp($row->last_seen_at),
                ],
            );
    }

    protected function customerLifetimeValue(): DataExportDefinition
    {
        return DataExportDefinition::make('customer_lifetime_value', 'Customer lifetime value', self::GROUP)
            ->describedAs('Total spend per customer, one row per currency they have paid in, with first and last payment dates.')
            ->withIcon('trending-up')
            ->requiring('admin.payments')
            ->filteredByDate('payments.paid_at', 'Paid date')
            ->aggregated()
            ->withColumns([
                'customer_id' => 'Customer ID',
                'username' => 'Username',
                'email' => 'Email',
                'customer' => 'Customer',
                'currency' => 'Currency',
                'paid_invoices' => 'Paid invoices',
                'gross_total' => 'Lifetime gross',
                'net_earnings' => 'Lifetime net',
                'average_invoice' => 'Average invoice',
                'first_paid_at' => 'First payment',
                'last_paid_at' => 'Last payment',
            ])
            ->sourcedFrom(
                fn () => DB::table('payments')
                    ->join('users', 'users.id', '=', 'payments.user_id')
                    ->where('payments.status', 'paid')
                    ->whereNotNull('payments.paid_at')
                    ->selectRaw('users.id as customer_id')
                    ->selectRaw('users.username as username')
                    ->selectRaw('users.email as email')
                    ->selectRaw('users.first_name as first_name')
                    ->selectRaw('users.last_name as last_name')
                    ->selectRaw('payments.currency as currency')
                    ->selectRaw('count(*) as paid_invoices')
                    ->selectRaw('sum(payments.total) as gross_total')
                    ->selectRaw('sum(payments.earnings) as net_earnings')
                    ->selectRaw('min(payments.paid_at) as first_paid_at')
                    ->selectRaw('max(payments.paid_at) as last_paid_at')
                    ->groupBy(
                        'users.id',
                        'users.username',
                        'users.email',
                        'users.first_name',
                        'users.last_name',
                        'payments.currency',
                    )
                    ->orderByRaw('sum(payments.total) desc'),
                fn (object $row): array => [
                    'customer_id' => DataExportValue::text($row->customer_id),
                    'username' => DataExportValue::text($row->username),
                    'email' => DataExportValue::text($row->email),
                    'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                    'currency' => DataExportValue::text($row->currency),
                    'paid_invoices' => DataExportValue::text($row->paid_invoices),
                    'gross_total' => DataExportValue::amount($row->gross_total),
                    'net_earnings' => DataExportValue::amount($row->net_earnings),
                    'average_invoice' => DataExportValue::amount(
                        (int) $row->paid_invoices > 0 ? (float) $row->gross_total / (int) $row->paid_invoices : 0,
                    ),
                    'first_paid_at' => DataExportValue::timestamp($row->first_paid_at),
                    'last_paid_at' => DataExportValue::timestamp($row->last_paid_at),
                ],
            );
    }

    protected function billingAddresses(): DataExportDefinition
    {
        return DataExportDefinition::make('billing_addresses', 'Billing addresses & tax IDs', self::GROUP)
            ->describedAs('Invoicing identities on file, including company names and VAT numbers used for tax validation.')
            ->withIcon('map-pin')
            ->requiring('admin.users')
            ->filteredByDate('addresses.created_at', 'Created date')
            ->withColumns([
                'customer_id' => 'Customer ID',
                'customer' => 'Customer',
                'email' => 'Email',
                'company_name' => 'Company name',
                'tax_id' => 'Tax / VAT ID',
                'address' => 'Address line 1',
                'address2' => 'Address line 2',
                'city' => 'City',
                'region' => 'Region',
                'zip_code' => 'Postal code',
                'country' => 'Country',
                'updated_at' => 'Last updated',
            ])
            ->sourcedFrom(
                fn () => DB::table('addresses')
                    ->leftJoin('users', 'users.id', '=', 'addresses.user_id')
                    ->orderByDesc('addresses.id')
                    ->select([
                        'addresses.company_name',
                        'addresses.tax_id',
                        'addresses.address',
                        'addresses.address2',
                        'addresses.city',
                        'addresses.region',
                        'addresses.zip_code',
                        'addresses.country',
                        'addresses.updated_at',
                        'addresses.user_id as customer_id',
                        'users.email',
                        'users.first_name',
                        'users.last_name',
                    ]),
                fn (object $row): array => [
                    'customer_id' => DataExportValue::text($row->customer_id),
                    'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                    'email' => DataExportValue::text($row->email),
                    'company_name' => DataExportValue::text($row->company_name),
                    'tax_id' => DataExportValue::text($row->tax_id),
                    'address' => DataExportValue::text($row->address),
                    'address2' => DataExportValue::text($row->address2),
                    'city' => DataExportValue::text($row->city),
                    'region' => DataExportValue::text($row->region),
                    'zip_code' => DataExportValue::text($row->zip_code),
                    'country' => DataExportValue::text($row->country),
                    'updated_at' => DataExportValue::timestamp($row->updated_at),
                ],
            );
    }

    protected function signupsByMonth(): DataExportDefinition
    {
        $month = DataExportGrammar::yearMonth('users.created_at');

        return DataExportDefinition::make('signups_by_month', 'Signups & conversion by month', self::GROUP)
            ->describedAs('New accounts per month and how many of them went on to pay an invoice — your signup to customer conversion rate.')
            ->withIcon('user-plus')
            ->requiring('admin.users')
            ->filteredByDate('users.created_at', 'Signup date')
            ->aggregated()
            ->withColumns([
                'month' => 'Month',
                'signups' => 'Signups',
                'verified_emails' => 'Verified emails',
                'paying_customers' => 'Became paying customers',
                'conversion_rate' => 'Conversion rate (%)',
            ])
            ->sourcedFrom(
                fn () => DB::table('users')
                    ->leftJoinSub(
                        DB::table('payments')
                            ->select('payments.user_id')
                            ->selectRaw('count(*) as paid_invoices')
                            ->where('payments.status', 'paid')
                            ->groupBy('payments.user_id'),
                        'paid',
                        'paid.user_id',
                        '=',
                        'users.id',
                    )
                    ->selectRaw("{$month} as month")
                    ->selectRaw('count(*) as signups')
                    ->selectRaw('sum(case when users.email_verified_at is not null then 1 else 0 end) as verified_emails')
                    ->selectRaw('sum(case when paid.paid_invoices > 0 then 1 else 0 end) as paying_customers')
                    ->groupByRaw($month)
                    ->orderByRaw("{$month} desc"),
                fn (object $row): array => [
                    'month' => DataExportValue::text($row->month),
                    'signups' => DataExportValue::text($row->signups),
                    'verified_emails' => DataExportValue::text($row->verified_emails),
                    'paying_customers' => DataExportValue::text($row->paying_customers),
                    'conversion_rate' => DataExportValue::percentage($row->paying_customers ?? 0, $row->signups),
                ],
            );
    }

    protected function bans(): DataExportDefinition
    {
        return DataExportDefinition::make('customer_bans', 'Bans & suspensions', self::GROUP)
            ->describedAs('Ban history with the reason, the staff member who issued it and whether it has been lifted.')
            ->withIcon('user-off')
            ->requiring('admin.users')
            ->filteredByDate('user_bans.created_at', 'Ban date')
            ->withColumns([
                'banned_at' => 'Banned at',
                'customer_id' => 'Customer ID',
                'customer' => 'Customer',
                'customer_email' => 'Customer email',
                'reason' => 'Reason',
                'ip_address' => 'IP address',
                'is_ip_ban' => 'IP ban',
                'banned_by' => 'Banned by',
                'expires_at' => 'Expires at',
                'lifted_at' => 'Lifted at',
                'lifted_by' => 'Lifted by',
            ])
            ->sourcedFrom(
                fn () => DB::table('user_bans')
                    ->leftJoin('users', 'users.id', '=', 'user_bans.user_id')
                    ->leftJoin('users as banned_by_user', 'banned_by_user.id', '=', 'user_bans.banned_by_id')
                    ->leftJoin('users as lifted_by_user', 'lifted_by_user.id', '=', 'user_bans.lifted_by_id')
                    ->orderByDesc('user_bans.id')
                    ->select([
                        'user_bans.created_at',
                        'user_bans.reason',
                        'user_bans.ip_address',
                        'user_bans.is_ip_ban',
                        'user_bans.expires_at',
                        'user_bans.lifted_at',
                        'user_bans.user_id as customer_id',
                        'users.email as customer_email',
                        'users.first_name',
                        'users.last_name',
                        'banned_by_user.email as banned_by',
                        'lifted_by_user.email as lifted_by',
                    ]),
                fn (object $row): array => [
                    'banned_at' => DataExportValue::timestamp($row->created_at),
                    'customer_id' => DataExportValue::text($row->customer_id),
                    'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                    'customer_email' => DataExportValue::text($row->customer_email),
                    'reason' => DataExportValue::text($row->reason),
                    'ip_address' => DataExportValue::text($row->ip_address),
                    'is_ip_ban' => DataExportValue::yesNo($row->is_ip_ban),
                    'banned_by' => DataExportValue::text($row->banned_by),
                    'expires_at' => DataExportValue::timestamp($row->expires_at),
                    'lifted_at' => DataExportValue::timestamp($row->lifted_at),
                    'lifted_by' => DataExportValue::text($row->lifted_by),
                ],
            );
    }
}
