<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $categories = DB::table('knowledgebase_categories')->pluck('id', 'slug');

        foreach ($this->articles($categories) as $article) {
            if (! $article['category_id']) {
                continue;
            }

            DB::table('knowledgebase_articles')->updateOrInsert(
                ['slug' => $article['slug']],
                [...$article, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('knowledgebase_articles')
            ->whereIn('slug', array_column($this->articles(collect()), 'slug'))
            ->delete();
    }

    /**
     * @param  Collection<string, int|string>|array<string, int|string>  $categories
     * @return list<array<string, mixed>>
     */
    protected function articles($categories): array
    {
        $id = fn (string $slug) => (int) data_get($categories, $slug);

        return [
            [
                'category_id' => $id('getting-started'),
                'author_id' => null,
                'title' => 'How to open a support ticket',
                'slug' => 'open-a-support-ticket',
                'excerpt' => 'When to open a ticket and what to include so we can help quickly.',
                'content' => implode("\n", [
                    'Search the knowledgebase first. If you still need help, open a ticket from **Tickets**.',
                    '',
                    '## What to include',
                    '',
                    '- The order or service this is about',
                    '- What you expected to happen',
                    '- What happened instead',
                    '- Any error messages',
                    '',
                    '## After you submit',
                    '',
                    'We will reply on the ticket. You can follow the conversation from the Tickets page.',
                ]),
                'tags' => json_encode(['tickets', 'support', 'getting-started']),
                'is_published' => true,
                'is_featured' => false,
                'hidden_from_guests' => false,
                'views_count' => 0,
                'helpful_count' => 0,
                'unhelpful_count' => 0,
                'published_at' => now(),
                'sort_order' => 20,
            ],
            [
                'category_id' => $id('billing'),
                'author_id' => null,
                'title' => 'How to pay an invoice',
                'slug' => 'pay-an-invoice',
                'excerpt' => 'Find unpaid invoices and complete payment from the client area.',
                'content' => implode("\n", [
                    'Invoices are created when you order a package or when a service renews.',
                    '',
                    '## Find the invoice',
                    '',
                    'Open **Payments** from the client area. Unpaid invoices are listed first.',
                    '',
                    '## Pay',
                    '',
                    'Open the invoice and choose a payment method. You can also add credit to your account balance and pay from there.',
                    '',
                    '## After payment',
                    '',
                    'Once the payment is marked paid, any related pending order continues provisioning automatically.',
                ]),
                'tags' => json_encode(['billing', 'invoices', 'payments']),
                'is_published' => true,
                'is_featured' => true,
                'hidden_from_guests' => false,
                'views_count' => 0,
                'helpful_count' => 0,
                'unhelpful_count' => 0,
                'published_at' => now(),
                'sort_order' => 10,
            ],
            [
                'category_id' => $id('services'),
                'author_id' => null,
                'title' => 'Managing your services',
                'slug' => 'managing-your-services',
                'excerpt' => 'View, renew, and manage services from the Orders page.',
                'content' => implode("\n", [
                    'Each purchase becomes an **order** — the service instance you manage in the client area.',
                    '',
                    '## Open a service',
                    '',
                    'Go to **Orders** and select the service. You will see its status, billing cycle, and any panel login details.',
                    '',
                    '## Statuses',
                    '',
                    '- **Pending** — waiting for payment or setup',
                    '- **Active** — running normally',
                    '- **Suspended** — temporarily stopped, often for an unpaid invoice',
                    '- **Terminated** — removed and no longer billed',
                    '',
                    '## Need a change?',
                    '',
                    'Use the service page for upgrades when available, or open a ticket if you need help.',
                ]),
                'tags' => json_encode(['services', 'orders']),
                'is_published' => true,
                'is_featured' => false,
                'hidden_from_guests' => false,
                'views_count' => 0,
                'helpful_count' => 0,
                'unhelpful_count' => 0,
                'published_at' => now(),
                'sort_order' => 10,
            ],
            [
                'category_id' => $id('technical'),
                'author_id' => null,
                'title' => 'I cannot access my service',
                'slug' => 'cannot-access-service',
                'excerpt' => 'Quick checks when a login, panel, or connection fails.',
                'content' => implode("\n", [
                    'If you cannot sign in to a provisioned service, work through these checks first.',
                    '',
                    '## Confirm the service is active',
                    '',
                    'Open the order. If it is **suspended** or **pending**, access will fail until that is resolved — often by paying an invoice.',
                    '',
                    '## Check the login details',
                    '',
                    'Use the username, password, and hostname shown on the order. Reset the service password from that page if the option is available.',
                    '',
                    '## Still stuck?',
                    '',
                    'Open a ticket in Technical Support. Include the order ID, the exact error, and when it started.',
                ]),
                'tags' => json_encode(['technical', 'access', 'login']),
                'is_published' => true,
                'is_featured' => false,
                'hidden_from_guests' => false,
                'views_count' => 0,
                'helpful_count' => 0,
                'unhelpful_count' => 0,
                'published_at' => now(),
                'sort_order' => 10,
            ],
        ];
    }
};
