<?php

namespace Extensions\Modules\Knowledgebase;

use App\Extensions\Foundation\ModuleExtension;

class Module extends ModuleExtension
{
    protected string $id = 'knowledgebase';

    protected string $name = 'Knowledgebase';

    protected string $description = 'Document answers with markdown articles, categories, search, and article view tracking.';

    protected string $type = 'Module';

    protected string $icon = 'book';

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
                    'name' => 'Knowledgebase',
                    'href' => '/knowledgebase',
                    'active' => 'knowledgebase',
                ],
            ],
            [
                'element' => 'client-dropdown-item',
                'attributes' => [
                    'name' => 'Knowledgebase',
                    'href' => '/knowledgebase',
                    'navigate' => true,
                ],
            ],
            [
                'element' => 'admin-sidebar-item-dropdown',
                'permission' => 'admin.knowledgebase',
                'attributes' => [
                    'name' => 'Knowledgebase',
                    'icon' => 'book',
                    'active' => ['knowledgebase', 'knowledgebase-categories'],
                    'items' => [
                        [
                            'name' => 'Articles',
                            'href' => '/admin/knowledgebase',
                            'active' => 'knowledgebase',
                            'icon' => 'file-text',
                            'permission' => 'admin.knowledgebase',
                        ],
                        [
                            'name' => 'Categories',
                            'href' => '/admin/knowledgebase/categories',
                            'active' => 'knowledgebase-categories',
                            'icon' => 'folder',
                            'permission' => 'admin.knowledgebase',
                        ],
                    ],
                ],
            ],
            [
                'element' => 'client-dashboard-top-view',
                'view' => 'knowledgebase::client_area.default.knowledgebase.widgets.popular-articles',
            ],
            [
                'element' => 'client-order-sidebar-bottom-view',
                'view' => 'knowledgebase::client_area.default.orders.widgets.get-help',
            ],
        ];
    }

    public function onInstall(): void
    {
        $this->migrate('extensions/Modules/Knowledgebase/Migrations');
    }

    public function onUninstall(): void
    {
        // Articles stay so documentation is not lost if the module is removed.
    }

    public function onEnable(): void
    {
        $this->migrate('extensions/Modules/Knowledgebase/Migrations');
    }

    public function onDisable(): void
    {
        //
    }
}
