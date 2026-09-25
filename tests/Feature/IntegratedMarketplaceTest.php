<?php

namespace Tests\Feature;

use App\Models\Extension;
use App\Models\Role;
use App\Models\User;
use App\Services\IntegratedMarketplace;
use App\Services\IntegratedMarketplaceInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

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
            ->get(route('admin.marketplace.index'))
            ->assertOk()
            ->assertSee('Demo module')
            ->assertSee('Featured')
            ->assertSee('Modules')
            ->assertSee('1 purchases')
            ->assertDontSee('<h2 class="mb-1">Marketplace</h2>', false);

        $this->actingAsMarketplaceAdmin()
            ->get(route('admin.marketplace.index'))
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
            ->get(route('admin.marketplace.show', 'demo-module'))
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
            ->assertSee('Install 1.0.0')
            ->call('setTab', 'reviews')
            ->assertSee('Great free tool')
            ->assertSee('Works well.');
    }

    public function test_resource_page_offers_install_for_the_latest_version(): void
    {
        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/demo-module' => Http::response([
                'data' => $this->resourcePayload(),
            ]),
        ]);

        $this->actingAsMarketplaceAdmin()
            ->get(route('admin.marketplace.show', 'demo-module'))
            ->assertOk()
            ->assertSee('Install 1.0.0');
    }

    public function test_one_click_install_extracts_a_compatible_version_and_enables_it(): void
    {
        $zip = $this->moduleZip();

        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/one-click-demo' => Http::response([
                'data' => $this->installableResource(hash_file('sha256', $zip)),
            ]),
            'http://wemx.test/api/v1/marketplace/resources/download/9' => Http::response(file_get_contents($zip), 200, [
                'Content-Type' => 'application/zip',
            ]),
        ]);

        $message = app(IntegratedMarketplaceInstaller::class)->install('one-click-demo', 9);

        $this->assertSame('One Click Demo 1.0.0 was installed.', $message);
        $this->assertFileExists(base_path('extensions/Modules/OneClickDemo/Module.php'));
        $this->assertSame('enabled', Extension::query()->where('identifier', 'one-click-demo')->value('status'));

        Extension::query()->where('identifier', 'one-click-demo')->delete();
        File::deleteDirectory(base_path('extensions/Modules/OneClickDemo'));
        @unlink($zip);
    }

    public function test_one_click_install_rejects_an_incompatible_wemx_version(): void
    {
        $payload = $this->installableResource(null);
        $payload['versions'][0]['wemx_version'] = '9.9.9';

        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/one-click-demo' => Http::response(['data' => $payload]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This version requires WemX 9.9.9.');

        app(IntegratedMarketplaceInstaller::class)->install('one-click-demo', 9);
    }

    public function test_one_click_install_rejects_a_version_that_is_not_on_the_integrated_marketplace(): void
    {
        $payload = $this->installableResource(null);
        $payload['versions'][0]['integrated_marketplace'] = false;

        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/one-click-demo' => Http::response(['data' => $payload]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not available on the integrated marketplace');

        app(IntegratedMarketplaceInstaller::class)->install('one-click-demo', 9);
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

    /**
     * @return array<string, mixed>
     */
    protected function installableResource(?string $checksum): array
    {
        return [
            'name' => 'One Click Demo',
            'slug' => 'one-click-demo',
            'price' => 'Free',
            'versions' => [[
                'id' => 9,
                'version' => '1.0.0',
                'wemx_version' => '*',
                'integrated_marketplace' => true,
                'extract_path' => 'extensions/Modules',
                'rename_extract_to' => 'OneClickDemo',
                'checksum' => $checksum,
            ]],
        ];
    }

    protected function moduleZip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mkt');
        $source = tempnam(sys_get_temp_dir(), 'mod');
        file_put_contents($source, <<<'PHP'
<?php

namespace Extensions\Modules\OneClickDemo;

use App\Extensions\Foundation\ModuleExtension;

class Module extends ModuleExtension
{
    protected string $id = 'one-click-demo';

    protected string $name = 'One Click Demo';

    protected string $description = 'Installed from the marketplace.';

    protected string $version = '1.0.0';

    protected string $marketplace_id = '9';

    protected array $wemxVersions = ['*'];

    public function elements(): array
    {
        return [];
    }

    public function onInstall(): void
    {
    }

    public function onUninstall(): void
    {
    }

    public function onEnable(): void
    {
    }

    public function onDisable(): void
    {
    }
}
PHP);

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFile($source, 'OneClickDemo/Module.php');
        $zip->close();
        @unlink($source);

        return $path;
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
