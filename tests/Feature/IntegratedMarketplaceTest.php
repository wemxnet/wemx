<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\IntegratedMarketplace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class IntegratedMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.installed' => true,
            'app.license_key' => 'WMX-TESTING-KEY',
            'services.marketplace.url' => 'http://wemx.test',
        ]);

        Cache::put('lcs_checked_at', now(), 21600);
        Cache::flush();
        Cache::put('lcs_checked_at', now(), 21600);

        Http::preventStrayRequests();
    }

    public function test_admin_can_browse_cached_marketplace_resources(): void
    {
        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources*' => Http::response($this->catalogPayload()),
        ]);

        $this->actingAsMarketplaceAdmin()
            ->get(route('admin.integrated-marketplace.index'))
            ->assertOk()
            ->assertSee('Demo module')
            ->assertSee('Featured')
            ->assertSee('Modules')
            ->assertSee('1 purchases')
            ->assertDontSee('<h2 class="mb-1">Marketplace</h2>', false);

        $this->actingAsMarketplaceAdmin()
            ->get(route('admin.integrated-marketplace.index'))
            ->assertOk();

        Http::assertSentCount(1);

        $cached = Cache::get('integrated-marketplace.catalog.'.md5((string) json_encode([
            'sort_by' => 'popular',
            'page' => 1,
            'per_page' => 18,
        ])));

        $this->assertIsArray($cached);
        $this->assertArrayNotHasKey('description', $cached['resources'][0]);
        $this->assertArrayNotHasKey('versions', $cached['resources'][0]);
        $this->assertSame('1.0.0', $cached['resources'][0]['latest_version']);
    }

    public function test_admin_resource_page_has_sections_and_marketplace_link(): void
    {
        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/demo-module' => Http::response([
                'data' => $this->resourcePayload(),
            ]),
        ]);

        $this->actingAsMarketplaceAdmin()
            ->get(route('admin.integrated-marketplace.show', 'demo-module'))
            ->assertOk()
            ->assertSee('Resource')
            ->assertSee('Versions')
            ->assertSee('Reviews')
            ->assertSee('View on marketplace')
            ->assertSee('http://wemx.test/marketplace/module/demo-module', false);

        Volt::test('admin_area.default.integrated-marketplace.livewire.resource', ['slug' => 'demo-module'])
            ->assertSee('A demo resource.')
            ->call('setTab', 'versions')
            ->assertSee('Initial release')
            ->assertSee('v1.0.0')
            ->call('setTab', 'reviews')
            ->assertSee('Great free tool')
            ->assertSee('Works well.');
    }

    public function test_catalog_errors_are_not_cached(): void
    {
        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources*' => Http::response(['message' => 'down'], 500),
        ]);

        $catalog = app(IntegratedMarketplace::class)->catalog();

        $this->assertNotNull($catalog['error']);
        $this->assertNull(Cache::get('integrated-marketplace.catalog.'.md5((string) json_encode([
            'sort_by' => 'popular',
            'page' => 1,
            'per_page' => 18,
        ]))));
    }

    /**
     * @return array<string, mixed>
     */
    protected function catalogPayload(): array
    {
        return [
            'current_page' => 1,
            'last_page' => 1,
            'total' => 1,
            'data' => [$this->resourcePayload()],
            'categories' => [
                ['slug' => 'module', 'name' => 'Modules'],
            ],
            'featured' => [$this->resourcePayload()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resourcePayload(): array
    {
        return [
            'id' => 1,
            'name' => 'Demo module',
            'slug' => 'demo-module',
            'short_description' => 'Adds extra client tools.',
            'description' => 'A demo resource.',
            'icon' => null,
            'initials' => 'DM',
            'price' => 'Free',
            'featured' => true,
            'official' => true,
            'views' => 12,
            'downloads' => 4,
            'purchases' => 1,
            'reviews_count' => 1,
            'reviews_avg' => 5,
            'source' => null,
            'website' => 'https://example.com',
            'docs' => null,
            'support' => null,
            'view_url' => 'http://wemx.test/marketplace/module/demo-module',
            'category' => ['id' => 1, 'slug' => 'module', 'name' => 'Modules'],
            'user' => ['username' => 'creator', 'avatar' => null, 'url' => null],
            'versions' => [[
                'id' => 9,
                'name' => 'Initial release',
                'version' => '1.0.0',
                'wemx_version' => '*',
                'changelog' => 'First public build.',
                'created_at' => now()->toIso8601String(),
                'integrated_marketplace' => true,
                'size_label' => '40.0 KB',
            ]],
            'reviews' => [[
                'id' => 3,
                'rating' => 5,
                'title' => 'Great free tool',
                'body' => 'Works well.',
                'created_at' => now()->toIso8601String(),
                'user' => ['username' => 'buyer', 'avatar' => null],
            ]],
        ];
    }

    protected function actingAsMarketplaceAdmin(): self
    {
        $admin = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->create([
            'name' => 'Marketplace '.$admin->id,
            'super_admin' => true,
        ]);

        DB::table('role_user')->insert([
            'role_id' => $role->id,
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->actingAs($admin->fresh())->withSession([
            'admin_reauthenticated_at' => now()->toDateTimeString(),
        ]);
    }
}
