<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $order = 10;

        foreach ($this->categories() as $category) {
            DB::table('marketplace_categories')->updateOrInsert(
                ['slug' => $category['slug']],
                [...$category, 'sort_order' => $order, 'is_visible' => true, 'created_at' => $now, 'updated_at' => $now]
            );

            $order += 10;
        }
    }

    public function down(): void
    {
        DB::table('marketplace_categories')
            ->whereIn('slug', array_column($this->categories(), 'slug'))
            ->delete();
    }

    /**
     * @return list<array{name: string, slug: string, description: string, icon: string, default_extract_path: string}>
     */
    protected function categories(): array
    {
        return [
            [
                'name' => 'Server',
                'slug' => 'server',
                'description' => 'Provisioning integrations for game panels, hosting control panels, and custom servers.',
                'icon' => 'server',
                'default_extract_path' => 'extensions/Servers',
            ],
            [
                'name' => 'Module',
                'slug' => 'module',
                'description' => 'Feature modules that extend billing, client area, or automation.',
                'icon' => 'package',
                'default_extract_path' => 'extensions/Modules',
            ],
            [
                'name' => 'Payment Gateway',
                'slug' => 'payment-gateway',
                'description' => 'Checkout processors that accept customer payments.',
                'icon' => 'credit-card',
                'default_extract_path' => 'extensions/Gateways',
            ],
            [
                'name' => 'Client Theme',
                'slug' => 'client-theme',
                'description' => 'Client area themes and storefront skins.',
                'icon' => 'palette',
                'default_extract_path' => 'resources/client_area',
            ],
            [
                'name' => 'Admin Theme',
                'slug' => 'admin-theme',
                'description' => 'Admin panel themes and layout packages.',
                'icon' => 'layout',
                'default_extract_path' => 'resources/admin_area',
            ],
            [
                'name' => 'Email Theme',
                'slug' => 'email-theme',
                'description' => 'Transactional email templates and branding.',
                'icon' => 'mail',
                'default_extract_path' => 'resources/email_templates',
            ],
            [
                'name' => 'Invoice Theme',
                'slug' => 'invoice-theme',
                'description' => 'Invoice PDF and HTML layouts.',
                'icon' => 'file-text',
                'default_extract_path' => 'resources/invoices',
            ],
            [
                'name' => 'Other',
                'slug' => 'other',
                'description' => 'Utilities, assets, and resources that do not fit another category.',
                'icon' => 'box',
                'default_extract_path' => '',
            ],
        ];
    }
};
