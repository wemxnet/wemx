<?php

namespace Extensions\Modules\Marketplace;

use App\Extensions\Foundation\ModuleExtension;

class Module extends ModuleExtension
{
    protected string $id = 'marketplace';

    protected string $name = 'Marketplace';

    protected string $description = 'A creator marketplace for WemX resources with licensing, one-click installs, and creator payouts.';

    protected string $type = 'Module';

    protected string $icon = 'store';

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

    public function elements(): array
    {
        return [
            [
                'element' => 'navigation-item',
                'attributes' => [
                    'name' => 'Marketplace',
                    'href' => '/marketplace',
                    'active' => 'marketplace',
                ],
            ],
            [
                'element' => 'client-dropdown-item',
                'attributes' => [
                    'name' => 'Marketplace',
                    'href' => '/marketplace',
                    'navigate' => true,
                ],
            ],
            [
                'element' => 'client-dropdown-item',
                'attributes' => [
                    'name' => 'Marketplace Purchases',
                    'href' => '/marketplace/library/purchases',
                    'navigate' => true,
                ],
            ],
            [
                'element' => 'client-dropdown-item',
                'attributes' => [
                    'name' => 'My Resources',
                    'href' => '/marketplace/library/resources',
                    'navigate' => true,
                ],
            ],
            [
                'element' => 'admin-sidebar-item-dropdown',
                'permission' => 'admin.marketplace',
                'attributes' => [
                    'name' => 'Marketplace Manager',
                    'icon' => 'store',
                    'active' => ['marketplace-manager', 'marketplace-manager-manage', 'marketplace-manager-sales', 'marketplace-manager-licenses'],
                    'items' => [
                        [
                            'name' => 'Overview',
                            'href' => '/admin/marketplace-manager',
                            'active' => 'marketplace-manager',
                            'icon' => 'layout-dashboard',
                            'permission' => 'admin.marketplace',
                        ],
                        [
                            'name' => 'Resources',
                            'href' => '/admin/marketplace-manager/resources',
                            'active' => 'marketplace-manager-manage',
                            'icon' => 'package',
                            'permission' => 'admin.marketplace.manage',
                        ],
                        [
                            'name' => 'Sales',
                            'href' => '/admin/marketplace-manager/sales',
                            'active' => 'marketplace-manager-sales',
                            'icon' => 'receipt',
                            'permission' => 'admin.marketplace',
                        ],
                        [
                            'name' => 'Purchases',
                            'href' => '/admin/marketplace-manager/licenses',
                            'active' => 'marketplace-manager-licenses',
                            'icon' => 'key',
                            'permission' => 'admin.marketplace',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function onInstall(): void
    {
        $this->migrate('extensions/Modules/Marketplace/Migrations');
    }

    public function onUninstall(): void
    {
        // Listings and licenses stay so creators are not wiped if the module is removed.
    }

    public function onEnable(): void
    {
        $this->migrate('extensions/Modules/Marketplace/Migrations');
    }

    public function onDisable(): void
    {
        //
    }
}
