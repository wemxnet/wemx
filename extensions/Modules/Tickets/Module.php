<?php

namespace Extensions\Modules\Tickets;

use App\Extensions\Foundation\ModuleExtension;
use Extensions\Modules\Tickets\Commands\AutoCloseInactiveTicketsCommand;
use Illuminate\Console\Scheduling\Schedule;

class Module extends ModuleExtension
{
    protected string $id = 'tickets';

    protected string $name = 'Tickets';

    protected string $description = 'Customer support tickets with departments, guest access, and a staff inbox.';

    protected string $type = 'Module';

    protected string $icon = 'ticket';

    protected string $version = '1.0.0';

    protected array $wemxVersions = ['*'];

    protected array $authors = [
        [
            'name' => 'WemX',
            'email' => 'mubeen@wemx.net',
        ],
    ];

    public function providers(): array
    {
        return [];
    }

    /**
     * @return array<int, class-string>
     */
    public function commands(): array
    {
        return [
            AutoCloseInactiveTicketsCommand::class,
        ];
    }

    public function schedule(Schedule $schedule): void
    {
        $schedule->command('tickets:auto-close')->hourly()->withoutOverlapping();
    }

    public function elements(): array
    {
        return [
            [
                'element' => 'navigation-item',
                'attributes' => [
                    'name' => 'Tickets',
                    'href' => '/tickets',
                    'active' => 'tickets',
                ],
            ],
            [
                'element' => 'client-dropdown-item',
                'attributes' => [
                    'name' => 'Tickets',
                    'href' => '/tickets',
                    'navigate' => true,
                ],
            ],
            [
                'element' => 'admin-sidebar-item-dropdown',
                'permission' => 'admin.tickets',
                'attributes' => [
                    'name' => 'Tickets',
                    'icon' => 'ticket',
                    'active' => ['tickets', 'ticket-departments'],
                    'items' => [
                        [
                            'name' => 'Overview',
                            'href' => '/admin/tickets',
                            'active' => 'tickets',
                            'icon' => 'inbox',
                            'permission' => 'admin.tickets',
                        ],
                        [
                            'name' => 'Create Ticket',
                            'href' => '/admin/tickets/create',
                            'active' => 'tickets',
                            'icon' => 'plus',
                            'permission' => 'admin.tickets.create',
                        ],
                        [
                            'name' => 'Departments',
                            'href' => '/admin/ticket-departments',
                            'active' => 'ticket-departments',
                            'icon' => 'folder',
                            'permission' => 'admin.ticket-departments',
                        ],
                    ],
                ],
            ],
            [
                'element' => 'client-dashboard-top-view',
                'view' => 'tickets::client_area.default.tickets.widgets.open-tickets',
            ],
            [
                'element' => 'client-order-sidebar-bottom-view',
                'view' => 'tickets::client_area.default.orders.widgets.get-help',
            ],
            [
                'element' => 'admin-order-sidebar-view',
                'view' => 'tickets::admin_area.default.orders.widgets.open-ticket',
            ],
            [
                'element' => 'admin-dashboard-main-view',
                'view' => 'tickets::admin_area.default.dashboard.widgets.needs-reply',
                'permission' => 'admin.tickets',
            ],
            [
                'element' => 'admin-customer-bottom-view',
                'view' => 'tickets::admin_area.default.users.widgets.user-tickets',
                'permission' => 'admin.tickets.view',
            ],
        ];
    }

    public function onInstall(): void
    {
        $this->migrate('extensions/Modules/Tickets/Migrations');
    }

    public function onUninstall(): void
    {
        // Ticket data is retained so support history is not lost if the module is removed.
    }

    public function onEnable(): void
    {
        //
    }

    public function onDisable(): void
    {
        //
    }
}
