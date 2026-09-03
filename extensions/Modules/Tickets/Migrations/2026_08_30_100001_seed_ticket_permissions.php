<?php

use Extensions\Modules\Tickets\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    protected array $permissions = [
        'admin.tickets' => 'View the support ticket inbox',
        'admin.tickets.view' => 'View and respond to tickets',
        'admin.tickets.create' => 'Create tickets on behalf of customers',
        'admin.tickets.update' => 'Update ticket department, priority, assignment, and status',
        'admin.tickets.delete' => 'Delete tickets',
        'admin.ticket-departments' => 'View ticket departments',
        'admin.ticket-departments.create' => 'Create ticket departments',
        'admin.ticket-departments.update' => 'Update ticket departments',
        'admin.ticket-departments.delete' => 'Delete ticket departments',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permission => $description) {
            DB::table('permissions')->updateOrInsert(
                ['permission' => $permission],
                ['description' => $description]
            );
        }

        $now = now();

        foreach ($this->departments() as $department) {
            DB::table('ticket_departments')->updateOrInsert(
                ['slug' => $department['slug']],
                [...$department, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $this->seedExtensionElements($now);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('permission', array_keys($this->permissions))
            ->delete();

        DB::table('ticket_departments')
            ->whereIn('slug', array_column($this->departments(), 'slug'))
            ->delete();

        DB::table('extension_elements')
            ->where('extension_identifier', 'tickets')
            ->whereNotNull('view')
            ->delete();
    }

    /**
     * Default departments for a hosting / digital-services panel.
     *
     * @return list<array<string, mixed>>
     */
    protected function departments(): array
    {
        return [
            [
                'name' => 'General Support',
                'slug' => 'general',
                'description' => 'Questions that are not billing, a specific service, or a sales enquiry.',
                'is_active' => true,
                'allow_guest_tickets' => false,
                'allow_guest_members' => false,
                'allow_invites' => true,
                'prefill_template' => implode("\n", [
                    '## How can we help?',
                    '',
                    '',
                    '## Related order (if any)',
                    '',
                    '',
                ]),
                'auto_response' => 'Thanks for reaching out. A member of our team will review your ticket and follow up shortly.',
                'notify_email' => null,
                'auto_close_days' => 0,
                'sort_order' => 10,
            ],
            [
                'name' => 'Technical Support',
                'slug' => 'technical',
                'description' => 'Problems with a provisioned service — access, connectivity, performance, or configuration.',
                'is_active' => true,
                'allow_guest_tickets' => false,
                'allow_guest_members' => false,
                'allow_invites' => true,
                'prefill_template' => implode("\n", [
                    '## Affected service / order',
                    '',
                    '',
                    '## What is not working?',
                    '',
                    '',
                    '## When did this start?',
                    '',
                    '',
                    '## What have you already tried?',
                    '',
                    '',
                    '## Error messages or logs',
                    '',
                    '',
                ]),
                'auto_response' => 'Thanks for the report. We will review the service on this ticket and get back to you.',
                'notify_email' => null,
                'auto_close_days' => 0,
                'sort_order' => 20,
            ],
            [
                'name' => 'Billing',
                'slug' => 'billing',
                'description' => 'Invoices, payments, refunds, cancellations, and account balance.',
                'is_active' => true,
                'allow_guest_tickets' => false,
                'allow_guest_members' => false,
                'allow_invites' => false,
                'prefill_template' => implode("\n", [
                    '## Invoice ID (if you have one)',
                    '',
                    '',
                    '## What do you need help with?',
                    '',
                    '',
                    '## Amount and date (if relevant)',
                    '',
                    '',
                ]),
                'auto_response' => 'Thanks — billing has received your ticket. We will review the account and any related invoices and reply as soon as we can.',
                'notify_email' => null,
                'auto_close_days' => 0,
                'sort_order' => 30,
            ],
            [
                'name' => 'Sales',
                'slug' => 'sales',
                'description' => 'Package questions, pricing, upgrades, and help placing an order.',
                'is_active' => true,
                'allow_guest_tickets' => true,
                'allow_guest_members' => false,
                'allow_invites' => true,
                'prefill_template' => implode("\n", [
                    '## What are you looking to run?',
                    '',
                    '',
                    '## How many services, sites, or players?',
                    '',
                    '',
                    '## Timeline or other requirements',
                    '',
                    '',
                ]),
                'auto_response' => 'Thanks for your interest. A member of the sales team will follow up with options and next steps.',
                'notify_email' => null,
                'auto_close_days' => 0,
                'sort_order' => 40,
            ],
            [
                'name' => 'Abuse',
                'slug' => 'abuse',
                'description' => 'Report spam, attacks, copyright complaints, or terms of service violations.',
                'is_active' => true,
                'allow_guest_tickets' => true,
                'allow_guest_members' => false,
                'allow_invites' => false,
                'prefill_template' => implode("\n", [
                    '## IP address, hostname, or service',
                    '',
                    '',
                    '## Date and time (include timezone)',
                    '',
                    '',
                    '## What happened?',
                    '',
                    '',
                    '## Evidence (logs, URLs, or headers)',
                    '',
                    '',
                ]),
                'auto_response' => 'This report has been received. Our team will investigate and take action if a violation is confirmed. We may not be able to share full details of the outcome.',
                'notify_email' => null,
                'auto_close_days' => 0,
                'sort_order' => 50,
            ],
        ];
    }

    protected function seedExtensionElements(DateTimeInterface $now): void
    {
        $ticketsEnabled = DB::table('extensions')
            ->where('identifier', 'tickets')
            ->where('status', 'enabled')
            ->exists();

        if (! $ticketsEnabled) {
            return;
        }

        foreach ((new Module)->elements() as $element) {
            if (empty($element['view'])) {
                continue;
            }

            $exists = DB::table('extension_elements')
                ->where('extension_identifier', 'tickets')
                ->where('element', $element['element'])
                ->where('view', $element['view'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('extension_elements')->insert([
                'extension_identifier' => 'tickets',
                'element' => $element['element'],
                'view' => $element['view'],
                'permission' => $element['permission'] ?? null,
                'attributes' => json_encode($element['attributes'] ?? []),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
