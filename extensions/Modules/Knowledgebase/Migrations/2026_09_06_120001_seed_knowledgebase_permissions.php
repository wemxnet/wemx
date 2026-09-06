<?php

use Extensions\Modules\Knowledgebase\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    protected array $permissions = [
        'admin.knowledgebase' => 'View the knowledgebase library',
        'admin.knowledgebase.create' => 'Create knowledgebase categories and articles',
        'admin.knowledgebase.update' => 'Update knowledgebase categories and articles',
        'admin.knowledgebase.delete' => 'Delete knowledgebase categories and articles',
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

        foreach ($this->categories() as $category) {
            DB::table('knowledgebase_categories')->updateOrInsert(
                ['slug' => $category['slug']],
                [...$category, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $gettingStartedId = DB::table('knowledgebase_categories')->where('slug', 'getting-started')->value('id');

        if ($gettingStartedId) {
            foreach ($this->articles((int) $gettingStartedId) as $article) {
                DB::table('knowledgebase_articles')->updateOrInsert(
                    ['slug' => $article['slug']],
                    [...$article, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        $this->seedExtensionElements($now);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('permission', array_keys($this->permissions))
            ->delete();

        DB::table('knowledgebase_articles')
            ->whereIn('slug', array_column($this->articles(0), 'slug'))
            ->delete();

        DB::table('knowledgebase_categories')
            ->whereIn('slug', array_column($this->categories(), 'slug'))
            ->delete();

        DB::table('extension_elements')
            ->where('extension_identifier', 'knowledgebase')
            ->whereNotNull('view')
            ->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function categories(): array
    {
        return [
            [
                'parent_id' => null,
                'name' => 'Getting Started',
                'slug' => 'getting-started',
                'description' => 'New to the client area? Start here.',
                'icon' => 'book',
                'is_visible' => true,
                'hidden_from_guests' => false,
                'sort_order' => 10,
            ],
            [
                'parent_id' => null,
                'name' => 'Billing',
                'slug' => 'billing',
                'description' => 'Invoices, payments, refunds, and your account balance.',
                'icon' => 'credit-card',
                'is_visible' => true,
                'hidden_from_guests' => false,
                'sort_order' => 20,
            ],
            [
                'parent_id' => null,
                'name' => 'Services',
                'slug' => 'services',
                'description' => 'Managing, upgrading, and accessing your services.',
                'icon' => 'server',
                'is_visible' => true,
                'hidden_from_guests' => false,
                'sort_order' => 30,
            ],
            [
                'parent_id' => null,
                'name' => 'Technical',
                'slug' => 'technical',
                'description' => 'Access, connectivity, and common configuration questions.',
                'icon' => 'wrench',
                'is_visible' => true,
                'hidden_from_guests' => false,
                'sort_order' => 40,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function articles(int $categoryId): array
    {
        return [
            [
                'category_id' => $categoryId,
                'author_id' => null,
                'title' => 'Welcome to the knowledgebase',
                'slug' => 'welcome',
                'excerpt' => 'How to find answers, search articles, and get further help.',
                'content' => implode("\n", [
                    'Use this knowledgebase to find answers before opening a ticket.',
                    '',
                    '## Search',
                    '',
                    'Type a few words into the search box on the knowledgebase home. Results match titles, summaries, and article content.',
                    '',
                    '## Browse by category',
                    '',
                    'Articles are grouped by topic — billing, services, and technical help. Open a category to see every article in it.',
                    '',
                    '## Still need help?',
                    '',
                    'If an article does not solve it, open a support ticket and mention what you already tried.',
                ]),
                'tags' => json_encode(['getting-started', 'help']),
                'is_published' => true,
                'is_featured' => true,
                'hidden_from_guests' => false,
                'views_count' => 0,
                'helpful_count' => 0,
                'unhelpful_count' => 0,
                'published_at' => now(),
                'sort_order' => 10,
            ],
        ];
    }

    protected function seedExtensionElements(DateTimeInterface $now): void
    {
        $enabled = DB::table('extensions')
            ->where('identifier', 'knowledgebase')
            ->where('status', 'enabled')
            ->exists();

        if (! $enabled) {
            return;
        }

        foreach ((new Module)->elements() as $element) {
            if (empty($element['view'])) {
                continue;
            }

            $exists = DB::table('extension_elements')
                ->where('extension_identifier', 'knowledgebase')
                ->where('element', $element['element'])
                ->where('view', $element['view'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('extension_elements')->insert([
                'extension_identifier' => 'knowledgebase',
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
