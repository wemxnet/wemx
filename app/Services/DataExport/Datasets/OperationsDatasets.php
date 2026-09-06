<?php

namespace App\Services\DataExport\Datasets;

use App\Services\DataExport\DataExportDatasetProvider;
use App\Services\DataExport\DataExportDefinition;
use App\Services\DataExport\DataExportValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationsDatasets implements DataExportDatasetProvider
{
    public const GROUP = 'Operations & compliance';

    public function definitions(): array
    {
        return [
            $this->provisionedAccounts(),
            $this->paymentGateways(),
            $this->taxRates(),
            $this->emailLog(),
            $this->auditLog(),
            $this->supportTickets(),
        ];
    }

    protected function provisionedAccounts(): DataExportDefinition
    {
        return DataExportDefinition::make('provisioned_accounts', 'Provisioned accounts', self::GROUP)
            ->describedAs('Accounts created on your server panels, mapped back to the order and customer. Panel passwords are never exported.')
            ->withIcon('server-cog')
            ->requiring('admin.servers.index')
            ->filteredByDate('server_accounts.created_at', 'Provisioned date')
            ->withColumns([
                'provisioned_at' => 'Provisioned at',
                'server_extension' => 'Server extension',
                'external_id' => 'Panel ID',
                'panel_username' => 'Panel username',
                'order_id' => 'Order ID',
                'order_status' => 'Order status',
                'package' => 'Package',
                'customer_id' => 'Customer ID',
                'customer' => 'Customer',
                'customer_email' => 'Customer email',
            ])
            ->sourcedFrom(
                fn () => DB::table('server_accounts')
                    ->leftJoin('users', 'users.id', '=', 'server_accounts.user_id')
                    ->leftJoin('orders', 'orders.id', '=', 'server_accounts.order_id')
                    ->leftJoin('packages', 'packages.id', '=', 'orders.package_id')
                    ->orderByDesc('server_accounts.id')
                    ->select([
                        'server_accounts.created_at',
                        'server_accounts.server as server_extension',
                        'server_accounts.external_id',
                        'server_accounts.username as panel_username',
                        'server_accounts.order_id',
                        'server_accounts.user_id as customer_id',
                        'orders.status as order_status',
                        'packages.name as package',
                        'users.email as customer_email',
                        'users.first_name',
                        'users.last_name',
                    ]),
                fn (object $row): array => [
                    'provisioned_at' => DataExportValue::timestamp($row->created_at),
                    'server_extension' => DataExportValue::text($row->server_extension),
                    'external_id' => DataExportValue::text($row->external_id),
                    'panel_username' => DataExportValue::text($row->panel_username),
                    'order_id' => DataExportValue::text($row->order_id),
                    'order_status' => DataExportValue::text($row->order_status),
                    'package' => DataExportValue::text($row->package),
                    'customer_id' => DataExportValue::text($row->customer_id),
                    'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                    'customer_email' => DataExportValue::text($row->customer_email),
                ],
            );
    }

    protected function paymentGateways(): DataExportDefinition
    {
        return DataExportDefinition::make('payment_gateways', 'Payment gateway setup', self::GROUP)
            ->describedAs('Configured payment methods and how much traffic each one handles. API keys and secrets are never exported.')
            ->withIcon('cash-register')
            ->requiring('admin.gateways.index')
            ->filteredByDate('gateway_configs.created_at', 'Created date')
            ->withColumns([
                'gateway_id' => 'Gateway ID',
                'display_name' => 'Display name',
                'extension_identifier' => 'Extension',
                'type' => 'Type',
                'is_active' => 'Active',
                'is_staff_only' => 'Staff only',
                'paid_invoices' => 'Paid invoices',
                'unpaid_invoices' => 'Unpaid invoices',
                'refunds' => 'Refunds',
                'active_subscriptions' => 'Active subscriptions',
                'created_at' => 'Created at',
            ])
            ->sourcedFrom(
                fn () => DB::table('gateway_configs')
                    ->orderBy('gateway_configs.display_name')
                    ->select([
                        'gateway_configs.id as gateway_id',
                        'gateway_configs.display_name',
                        'gateway_configs.extension_identifier',
                        'gateway_configs.type',
                        'gateway_configs.is_active',
                        'gateway_configs.is_staff_only',
                        'gateway_configs.created_at',
                    ])
                    ->selectSub(
                        DB::table('payments')
                            ->selectRaw('count(*)')
                            ->whereColumn('payments.gateway_config_id', 'gateway_configs.id')
                            ->where('payments.status', 'paid'),
                        'paid_invoices',
                    )
                    ->selectSub(
                        DB::table('payments')
                            ->selectRaw('count(*)')
                            ->whereColumn('payments.gateway_config_id', 'gateway_configs.id')
                            ->where('payments.status', 'unpaid'),
                        'unpaid_invoices',
                    )
                    ->selectSub(
                        DB::table('payment_refunds')
                            ->selectRaw('count(*)')
                            ->whereColumn('payment_refunds.gateway_config_id', 'gateway_configs.id'),
                        'refunds',
                    )
                    ->selectSub(
                        DB::table('subscriptions')
                            ->selectRaw('count(*)')
                            ->whereColumn('subscriptions.gateway_config_id', 'gateway_configs.id')
                            ->where('subscriptions.status', 'active'),
                        'active_subscriptions',
                    ),
                fn (object $row): array => [
                    'gateway_id' => DataExportValue::text($row->gateway_id),
                    'display_name' => DataExportValue::text($row->display_name),
                    'extension_identifier' => DataExportValue::text($row->extension_identifier),
                    'type' => DataExportValue::text($row->type),
                    'is_active' => DataExportValue::yesNo($row->is_active),
                    'is_staff_only' => DataExportValue::yesNo($row->is_staff_only),
                    'paid_invoices' => DataExportValue::text($row->paid_invoices),
                    'unpaid_invoices' => DataExportValue::text($row->unpaid_invoices),
                    'refunds' => DataExportValue::text($row->refunds),
                    'active_subscriptions' => DataExportValue::text($row->active_subscriptions),
                    'created_at' => DataExportValue::timestamp($row->created_at),
                ],
            );
    }

    protected function taxRates(): DataExportDefinition
    {
        return DataExportDefinition::make('tax_rates', 'Configured tax rates', self::GROUP)
            ->describedAs('Sales tax and VAT rates per country and state, as currently configured. Use it to review rates before a filing period.')
            ->withIcon('receipt-tax')
            ->requiring('admin.taxes.index')
            ->withColumns([
                'country_code' => 'Country code',
                'country_tax_name' => 'Country tax name',
                'country_tax_rate' => 'Country rate (%)',
                'country_active' => 'Country active',
                'state_code' => 'State code',
                'state_tax_name' => 'State tax name',
                'state_tax_rate' => 'State rate (%)',
                'state_active' => 'State active',
            ])
            ->sourcedFrom(
                fn () => DB::table('sales_tax_countries')
                    ->leftJoin('sales_tax_states', 'sales_tax_states.country_id', '=', 'sales_tax_countries.id')
                    ->orderBy('sales_tax_countries.country_code')
                    ->orderBy('sales_tax_states.state_code')
                    ->select([
                        'sales_tax_countries.country_code',
                        'sales_tax_countries.sales_tax_name as country_tax_name',
                        'sales_tax_countries.sales_tax_rate as country_tax_rate',
                        'sales_tax_countries.is_active as country_active',
                        'sales_tax_states.state_code',
                        'sales_tax_states.sales_tax_name as state_tax_name',
                        'sales_tax_states.sales_tax_rate as state_tax_rate',
                        'sales_tax_states.is_active as state_active',
                    ]),
                fn (object $row): array => [
                    'country_code' => DataExportValue::text($row->country_code),
                    'country_tax_name' => DataExportValue::text($row->country_tax_name),
                    'country_tax_rate' => DataExportValue::rate($row->country_tax_rate),
                    'country_active' => DataExportValue::yesNo($row->country_active),
                    'state_code' => DataExportValue::text($row->state_code),
                    'state_tax_name' => DataExportValue::text($row->state_tax_name),
                    'state_tax_rate' => $row->state_code === null ? '' : DataExportValue::rate($row->state_tax_rate),
                    'state_active' => $row->state_code === null ? '' : DataExportValue::yesNo($row->state_active),
                ],
            );
    }

    protected function emailLog(): DataExportDefinition
    {
        return DataExportDefinition::make('email_log', 'Sent email log', self::GROUP)
            ->describedAs('Delivery history for transactional email, including whether the customer opened it. Handy for billing disputes.')
            ->withIcon('send')
            ->requiring('admin.emails.index')
            ->filteredByDate('emails.created_at', 'Sent date')
            ->withColumns([
                'sent_at' => 'Sent at',
                'recipient' => 'Recipient',
                'sender' => 'Sender',
                'subject' => 'Subject',
                'template' => 'Template',
                'status' => 'Status',
                'seen_at' => 'Opened at',
                'customer_id' => 'Customer ID',
                'customer' => 'Customer',
                'related_to' => 'Related to',
                'related_id' => 'Related ID',
            ])
            ->sourcedFrom(
                fn () => DB::table('emails')
                    ->leftJoin('users', 'users.id', '=', 'emails.user_id')
                    ->orderByDesc('emails.id')
                    ->select([
                        'emails.created_at',
                        'emails.to as recipient',
                        'emails.from as sender',
                        'emails.subject',
                        'emails.identifier as template',
                        'emails.status',
                        'emails.seen_at',
                        'emails.mailable_type',
                        'emails.mailable_id',
                        'emails.user_id as customer_id',
                        'users.first_name',
                        'users.last_name',
                    ]),
                fn (object $row): array => [
                    'sent_at' => DataExportValue::timestamp($row->created_at),
                    'recipient' => DataExportValue::text($row->recipient),
                    'sender' => DataExportValue::text($row->sender),
                    'subject' => DataExportValue::text($row->subject),
                    'template' => DataExportValue::text($row->template),
                    'status' => DataExportValue::text($row->status),
                    'seen_at' => DataExportValue::timestamp($row->seen_at),
                    'customer_id' => DataExportValue::text($row->customer_id),
                    'customer' => DataExportValue::fullName($row->first_name, $row->last_name),
                    'related_to' => DataExportValue::className($row->mailable_type),
                    'related_id' => DataExportValue::text($row->mailable_id),
                ],
            );
    }

    protected function auditLog(): DataExportDefinition
    {
        return DataExportDefinition::make('audit_log', 'Audit trail', self::GROUP)
            ->describedAs('Who changed what and when, with the previous and new values. The export auditors and chargeback disputes ask for.')
            ->withIcon('history')
            ->requiring('admin.users')
            ->filteredByDate('activity_logs.created_at', 'Event date')
            ->withColumns([
                'occurred_at' => 'Occurred at',
                'event' => 'Event',
                'description' => 'Description',
                'tag' => 'Tag',
                'actor_id' => 'Actor ID',
                'actor' => 'Actor',
                'actor_email' => 'Actor email',
                'subject_type' => 'Subject type',
                'subject_id' => 'Subject ID',
                'field' => 'Field',
                'old_value' => 'Old value',
                'new_value' => 'New value',
                'ip_address' => 'IP address',
            ])
            ->sourcedFrom(
                fn () => DB::table('activity_logs')
                    ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
                    ->orderByDesc('activity_logs.id')
                    ->select([
                        'activity_logs.created_at',
                        'activity_logs.event',
                        'activity_logs.description',
                        'activity_logs.tag',
                        'activity_logs.model_type',
                        'activity_logs.model_id',
                        'activity_logs.field',
                        'activity_logs.old_value',
                        'activity_logs.new_value',
                        'activity_logs.ip_address',
                        'activity_logs.user_id as actor_id',
                        'users.email as actor_email',
                        'users.first_name',
                        'users.last_name',
                    ]),
                fn (object $row): array => [
                    'occurred_at' => DataExportValue::timestamp($row->created_at),
                    'event' => DataExportValue::text($row->event),
                    'description' => DataExportValue::text($row->description),
                    'tag' => DataExportValue::text($row->tag),
                    'actor_id' => DataExportValue::text($row->actor_id),
                    'actor' => DataExportValue::fullName($row->first_name, $row->last_name),
                    'actor_email' => DataExportValue::text($row->actor_email),
                    'subject_type' => DataExportValue::className($row->model_type),
                    'subject_id' => DataExportValue::text($row->model_id),
                    'field' => DataExportValue::text($row->field),
                    'old_value' => DataExportValue::text($row->old_value),
                    'new_value' => DataExportValue::text($row->new_value),
                    'ip_address' => DataExportValue::text($row->ip_address),
                ],
            );
    }

    protected function supportTickets(): DataExportDefinition
    {
        return DataExportDefinition::make('support_tickets', 'Support tickets', self::GROUP)
            ->describedAs('Ticket queue history with department, priority, assignee and reply counts for measuring support load.')
            ->withIcon('lifebuoy')
            ->requiring('admin.tickets')
            ->availableWhen(fn (): bool => Schema::hasTable('tickets'))
            ->filteredByDate('tickets.created_at', 'Opened date')
            ->withColumns([
                'opened_at' => 'Opened at',
                'number' => 'Ticket number',
                'title' => 'Subject',
                'department' => 'Department',
                'status' => 'Status',
                'priority' => 'Priority',
                'customer' => 'Customer',
                'customer_email' => 'Customer email',
                'order_id' => 'Related order',
                'assigned_to' => 'Assigned to',
                'replies' => 'Replies',
                'last_reply_from' => 'Last reply from',
                'last_replied_at' => 'Last replied at',
                'closed_at' => 'Closed at',
                'days_to_close' => 'Days to close',
            ])
            ->sourcedFrom(
                fn () => DB::table('tickets')
                    ->leftJoin('ticket_departments', 'ticket_departments.id', '=', 'tickets.department_id')
                    ->leftJoin('users', 'users.id', '=', 'tickets.user_id')
                    ->leftJoin('users as assignee', 'assignee.id', '=', 'tickets.assigned_to')
                    ->orderByDesc('tickets.id')
                    ->select([
                        'tickets.number',
                        'tickets.title',
                        'tickets.status',
                        'tickets.priority',
                        'tickets.order_id',
                        'tickets.last_reply_from',
                        'tickets.last_replied_at',
                        'tickets.closed_at',
                        'tickets.created_at',
                        'tickets.guest_name',
                        'tickets.guest_email',
                        'ticket_departments.name as department',
                        'users.email as customer_email',
                        'users.first_name',
                        'users.last_name',
                        'assignee.email as assigned_to',
                    ])
                    ->selectSub(
                        DB::table('ticket_messages')
                            ->selectRaw('count(*)')
                            ->whereColumn('ticket_messages.ticket_id', 'tickets.id')
                            ->where('ticket_messages.type', 'comment'),
                        'replies',
                    ),
                fn (object $row): array => [
                    'opened_at' => DataExportValue::timestamp($row->created_at),
                    'number' => DataExportValue::text($row->number),
                    'title' => DataExportValue::text($row->title),
                    'department' => DataExportValue::text($row->department),
                    'status' => DataExportValue::text($row->status),
                    'priority' => DataExportValue::text($row->priority),
                    'customer' => DataExportValue::fullName($row->first_name, $row->last_name)
                        ?: DataExportValue::text($row->guest_name),
                    'customer_email' => DataExportValue::text($row->customer_email ?? $row->guest_email),
                    'order_id' => DataExportValue::text($row->order_id),
                    'assigned_to' => DataExportValue::text($row->assigned_to),
                    'replies' => DataExportValue::text($row->replies),
                    'last_reply_from' => DataExportValue::text($row->last_reply_from),
                    'last_replied_at' => DataExportValue::timestamp($row->last_replied_at),
                    'closed_at' => DataExportValue::timestamp($row->closed_at),
                    'days_to_close' => $row->closed_at === null
                        ? ''
                        : DataExportValue::daysBetween($row->created_at, $row->closed_at),
                ],
            );
    }
}
