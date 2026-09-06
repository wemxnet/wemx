<?php

namespace Extensions\Modules\Downloads;

use App\Extensions\Foundation\ModuleExtension;

class Module extends ModuleExtension
{
    protected string $id = 'downloads';

    protected string $name = 'Downloads';

    protected string $description = 'Share files with customers from sortable folders, with guest, login, and package access rules.';

    protected string $type = 'Module';

    protected string $icon = 'download';

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
                    'name' => 'Downloads',
                    'href' => '/downloads',
                    'active' => 'downloads',
                ],
            ],
            [
                'element' => 'client-dropdown-item',
                'attributes' => [
                    'name' => 'Downloads',
                    'href' => '/downloads',
                    'navigate' => true,
                ],
            ],
            [
                'element' => 'admin-sidebar-item',
                'permission' => 'admin.downloads',
                'attributes' => [
                    'name' => 'Downloads',
                    'href' => '/admin/downloads',
                    'active' => 'downloads',
                    'icon' => 'download',
                ],
            ],
        ];
    }

    public function onInstall(): void
    {
        $this->migrate('extensions/Modules/Downloads/Migrations');
    }

    public function onUninstall(): void
    {
        // Files stay on disk so a reinstall can recover them if the tables are still present.
    }

    public function onEnable(): void
    {
        $this->migrate('extensions/Modules/Downloads/Migrations');
    }

    public function onDisable(): void
    {
        //
    }
}
