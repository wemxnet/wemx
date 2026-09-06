<?php

namespace Tests\Feature;

use App\Models\User;
use Extensions\Modules\Knowledgebase\Models\KnowledgebaseArticle;
use Extensions\Modules\Knowledgebase\Models\KnowledgebaseArticleVote;
use Extensions\Modules\Knowledgebase\Models\KnowledgebaseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KnowledgebaseTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected KnowledgebaseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => 'extensions/Modules/Knowledgebase/Migrations',
        ]);

        if (! Route::has('knowledgebase.index')) {
            require base_path('extensions/Modules/Knowledgebase/routes.php');
        }

        $this->admin = User::factory()->create();
        $this->customer = User::factory()->create();
        $this->category = KnowledgebaseCategory::query()->where('slug', 'getting-started')->firstOrFail();
    }

    public function test_seeded_categories_and_welcome_article_exist(): void
    {
        $this->assertTrue(KnowledgebaseCategory::query()->where('slug', 'billing')->exists());
        $this->assertTrue(KnowledgebaseArticle::query()->where('slug', 'welcome')->exists());

        $welcome = KnowledgebaseArticle::query()->where('slug', 'welcome')->first();
        $this->assertTrue($welcome->is_featured);
        $this->assertTrue($welcome->is_published);
        $this->assertStringContainsString('Search', $welcome->content);
    }

    public function test_admin_can_create_a_category(): void
    {
        $category = KnowledgebaseCategory::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'name' => 'Account Access',
            'description' => 'Passwords and two-factor authentication.',
            'icon' => 'life-buoy',
        ]);

        $this->assertSame('account-access', $category->slug);
        $this->assertTrue($category->is_visible);
        $this->assertGreaterThan(0, $category->sort_order);
    }

    public function test_categories_cannot_nest_more_than_one_level(): void
    {
        $parent = KnowledgebaseCategory::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'name' => 'Parent',
        ]);

        $child = KnowledgebaseCategory::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);

        $this->assertSame($parent->id, $child->parent_id);

        $this->expectException(ValidationException::class);

        KnowledgebaseCategory::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'name' => 'Grandchild',
            'parent_id' => $child->id,
        ]);
    }

    public function test_category_cannot_be_deleted_while_it_has_articles(): void
    {
        $this->expectException(ValidationException::class);

        KnowledgebaseCategory::actions()->deleteAsAdmin([
            'admin_user_id' => $this->admin->id,
            'category_id' => $this->category->id,
        ]);
    }

    public function test_admin_can_create_a_markdown_article(): void
    {
        $article = KnowledgebaseArticle::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'category_id' => $this->category->id,
            'title' => 'How invoices work',
            'content' => "## Overview\n\nInvoices are created when an **order** renews.\n\n### Payment\n\nPay from the client area.",
            'tags' => 'billing, invoices',
            'is_published' => true,
            'is_featured' => true,
        ]);

        $this->assertSame('how-invoices-work', $article->slug);
        $this->assertSame($this->admin->id, $article->author_id);
        $this->assertSame(['billing', 'invoices'], $article->tags);
        $this->assertNotNull($article->published_at);
        $this->assertStringContainsString('Invoices are created', $article->excerpt);
        $this->assertStringContainsString('<strong>order</strong>', $article->renderedContent());
        $this->assertCount(2, $article->tableOfContents());
        $this->assertSame('overview', $article->tableOfContents()[0]['id']);
    }

    public function test_customer_cannot_manage_articles(): void
    {
        $this->expectException(ValidationException::class);

        KnowledgebaseArticle::actions()->createAsAdmin([
            'admin_user_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'title' => 'Nope',
            'content' => 'Should fail',
        ]);
    }

    public function test_guests_cannot_see_client_only_or_draft_articles(): void
    {
        $private = KnowledgebaseArticle::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'category_id' => $this->category->id,
            'title' => 'Private article',
            'content' => 'Only clients',
            'hidden_from_guests' => true,
        ]);

        $draft = KnowledgebaseArticle::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'category_id' => $this->category->id,
            'title' => 'Draft article',
            'content' => 'Not ready',
            'is_published' => false,
        ]);

        $this->assertFalse($private->isVisibleTo(null));
        $this->assertTrue($private->isVisibleTo($this->customer));
        $this->assertFalse($draft->isVisibleTo(null));
        $this->assertFalse($draft->isVisibleTo($this->customer));
        $this->assertTrue($draft->isVisibleTo($this->admin));
    }

    public function test_search_matches_title_and_content(): void
    {
        KnowledgebaseArticle::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'category_id' => $this->category->id,
            'title' => 'Reset your password',
            'content' => 'Use the forgot password form on the login page.',
        ]);

        $results = KnowledgebaseArticle::query()->visibleTo(null)->search('forgot password')->get();

        $this->assertTrue($results->contains(fn (KnowledgebaseArticle $article) => $article->title === 'Reset your password'));
    }

    public function test_article_views_increment_once_per_visitor_per_day(): void
    {
        $article = KnowledgebaseArticle::query()->where('slug', 'welcome')->firstOrFail();

        KnowledgebaseArticle::actions()->recordView($article, $this->customer);
        KnowledgebaseArticle::actions()->recordView($article, $this->customer);
        KnowledgebaseArticle::actions()->recordView($article, $this->admin);

        $article->refresh();

        $this->assertSame(2, $article->views_count);
        $this->assertSame(2, $article->views()->count());
    }

    public function test_visitors_can_vote_and_change_their_vote(): void
    {
        $article = KnowledgebaseArticle::query()->where('slug', 'welcome')->firstOrFail();

        KnowledgebaseArticle::actions()->vote([
            'article_id' => $article->id,
            'user_id' => $this->customer->id,
            'is_helpful' => true,
        ]);

        KnowledgebaseArticle::actions()->vote([
            'article_id' => $article->id,
            'user_id' => $this->customer->id,
            'is_helpful' => true,
        ]);

        $article->refresh();
        $this->assertSame(1, $article->helpful_count);
        $this->assertSame(0, $article->unhelpful_count);
        $this->assertSame(100, $article->helpfulPercent());

        KnowledgebaseArticle::actions()->vote([
            'article_id' => $article->id,
            'user_id' => $this->customer->id,
            'is_helpful' => false,
        ]);

        $article->refresh();
        $this->assertSame(0, $article->helpful_count);
        $this->assertSame(1, $article->unhelpful_count);
        $this->assertSame(1, KnowledgebaseArticleVote::query()->where('article_id', $article->id)->count());
    }

    public function test_related_articles_prefer_the_same_category(): void
    {
        $first = KnowledgebaseArticle::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'category_id' => $this->category->id,
            'title' => 'First related',
            'content' => 'Related body',
            'tags' => 'getting-started',
        ]);

        $second = KnowledgebaseArticle::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'category_id' => $this->category->id,
            'title' => 'Second related',
            'content' => 'Also related',
            'tags' => 'getting-started',
        ]);

        $related = $first->related($this->customer);

        $this->assertTrue($related->contains('id', $second->id));
        $this->assertTrue($related->contains(fn (KnowledgebaseArticle $article) => $article->slug === 'welcome'));
    }

    public function test_slug_collision_gets_a_suffix(): void
    {
        KnowledgebaseArticle::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'category_id' => $this->category->id,
            'title' => 'Welcome',
            'content' => 'Another welcome article',
        ]);

        $this->assertTrue(KnowledgebaseArticle::query()->where('slug', 'welcome-1')->exists());
    }

    public function test_category_breadcrumbs_include_the_current_category(): void
    {
        $parent = KnowledgebaseCategory::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'name' => 'Docs Parent',
        ]);

        $child = KnowledgebaseCategory::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'name' => 'Docs Child',
            'parent_id' => $parent->id,
        ]);

        $trail = $child->breadcrumbs();

        $this->assertSame(['Docs Parent', 'Docs Child'], $trail->pluck('name')->all());
    }

    public function test_unpublished_article_is_excluded_from_public_search(): void
    {
        KnowledgebaseArticle::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'category_id' => $this->category->id,
            'title' => 'Hidden draft secret',
            'content' => 'secret-token-xyz',
            'is_published' => false,
        ]);

        $this->assertFalse(
            KnowledgebaseArticle::query()->visibleTo(null)->search('secret-token-xyz')->exists()
        );
        $this->assertTrue(
            KnowledgebaseArticle::query()->visibleTo($this->admin)->search('secret-token-xyz')->exists()
        );
    }
}
