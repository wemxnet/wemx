<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use Extensions\Modules\Downloads\Models\DownloadFile;
use Extensions\Modules\Downloads\Models\DownloadFolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DownloadsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => 'extensions/Modules/Downloads/Migrations',
        ]);

        if (! Route::has('downloads.download')) {
            require base_path('extensions/Modules/Downloads/routes.php');
            $this->app['router']->getRoutes()->refreshNameLookups();
        }

        $this->app['view']->addNamespace('downloads', base_path('extensions/Modules/Downloads/Views'));

        config([
            'app.installed' => true,
            'app.license_key' => 'WMX-TESTING-KEY',
        ]);
        Cache::put('lcs_checked_at', now(), 21600);

        Storage::fake('local');

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->customer = User::factory()->create(['status' => 'active']);
    }

    public function test_admin_can_create_a_sortable_folder(): void
    {
        $folder = DownloadFolder::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'name' => 'Game files',
            'description' => 'Mods and extras',
            'is_visible' => true,
        ]);

        $this->assertSame('Game files', $folder->name);
        $this->assertSame('game-files', $folder->slug);
        $this->assertTrue($folder->is_visible);
        $this->assertGreaterThan(0, $folder->sort_order);
    }

    public function test_admin_can_upload_a_file_with_access_options(): void
    {
        $folder = $this->createFolder();
        $package = $this->createPackage();

        $file = DownloadFile::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'folder_id' => $folder->id,
            'name' => 'Server pack',
            'description' => 'Includes **mods** and configs.',
            'version' => '1.4.0',
            'file' => UploadedFile::fake()->create('server-pack.zip', 120, 'application/zip'),
            'allow_guests' => false,
            'package_ids' => [$package->id],
            'require_active_order' => true,
            'hidden_until_eligible' => true,
        ]);

        $this->assertSame('Server pack', $file->name);
        $this->assertSame('1.4.0', $file->version);
        $this->assertSame([$package->id], $file->requiredPackageIds());
        $this->assertTrue($file->hidden_until_eligible);
        $this->assertStringContainsString('mods', $file->renderedDescription());
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_guest_can_download_a_public_file(): void
    {
        $file = $this->createFile([
            'allow_guests' => true,
            'name' => 'Public brochure',
            'original' => 'brochure.pdf',
        ]);

        $this->assertTrue($file->canBeDownloadedBy(null, '127.0.0.1'));

        $response = $this->get(route('downloads.download', [$file->folder, $file]));

        $response->assertOk();
        $response->assertDownload('Public brochure.pdf');
        $this->assertSame(1, $file->fresh()->download_count);
        $this->assertDatabaseHas('download_logs', [
            'file_id' => $file->id,
            'user_id' => null,
        ]);
    }

    public function test_guest_cannot_download_a_customer_only_file(): void
    {
        $file = $this->createFile([
            'allow_guests' => false,
        ]);

        $this->assertFalse($file->canBeDownloadedBy(null, '127.0.0.1'));
        $this->assertSame(DownloadFile::DENIAL_LOGIN, $file->denialReason(null, '127.0.0.1'));

        $this->get(route('downloads.download', [$file->folder, $file]))
            ->assertForbidden();
    }

    public function test_customer_without_required_package_cannot_download(): void
    {
        $package = $this->createPackage();
        $file = $this->createFile([
            'package_ids' => [$package->id],
            'hidden_until_eligible' => false,
        ]);

        $this->assertFalse($file->canBeDownloadedBy($this->customer, '127.0.0.1'));
        $this->assertSame(DownloadFile::DENIAL_PACKAGE, $file->denialReason($this->customer, '127.0.0.1'));
        $this->assertTrue($file->isVisibleTo($this->customer));

        $this->actingAs($this->customer)
            ->get(route('downloads.download', [$file->folder, $file]))
            ->assertForbidden();
    }

    public function test_customer_with_active_package_order_can_download(): void
    {
        $package = $this->createPackage();
        $this->createOrderFor($this->customer, $package, 'active');

        $file = $this->createFile([
            'package_ids' => [$package->id],
            'require_active_order' => true,
        ]);

        $this->assertTrue($file->canBeDownloadedBy($this->customer, '127.0.0.1'));

        $this->actingAs($this->customer)
            ->get(route('downloads.download', [$file->folder, $file]))
            ->assertOk()
            ->assertDownload();
    }

    public function test_suspended_order_does_not_count_when_active_is_required(): void
    {
        $package = $this->createPackage();
        $this->createOrderFor($this->customer, $package, 'suspended');

        $file = $this->createFile([
            'package_ids' => [$package->id],
            'require_active_order' => true,
        ]);

        $this->assertFalse($file->canBeDownloadedBy($this->customer, '127.0.0.1'));

        $file->update(['require_active_order' => false]);

        $this->assertTrue($file->fresh()->canBeDownloadedBy($this->customer, '127.0.0.1'));
    }

    public function test_require_any_order_allows_customers_with_a_service(): void
    {
        $file = $this->createFile([
            'require_any_order' => true,
        ]);

        $this->assertFalse($file->canBeDownloadedBy($this->customer, '127.0.0.1'));

        $this->createOrderFor($this->customer, $this->createPackage(), 'active');

        $this->assertTrue($file->canBeDownloadedBy($this->customer, '127.0.0.1'));
    }

    public function test_unpublished_file_is_hidden_and_not_found(): void
    {
        $file = $this->createFile([
            'allow_guests' => true,
            'is_published' => false,
        ]);

        $this->assertFalse($file->isVisibleTo(null));
        $this->assertFalse($file->isVisibleTo($this->customer));
        $this->assertTrue($file->isVisibleTo($this->admin));

        $this->get(route('downloads.folder', $file->folder))->assertNotFound();
        $this->get(route('downloads.download', [$file->folder, $file]))->assertNotFound();
    }

    public function test_hidden_until_eligible_hides_locked_files_from_listing(): void
    {
        $package = $this->createPackage();
        $file = $this->createFile([
            'package_ids' => [$package->id],
            'hidden_until_eligible' => true,
        ]);

        $this->assertFalse($file->isVisibleTo($this->customer));
        $this->get(route('downloads.index'))->assertDontSee($file->name);

        $this->createOrderFor($this->customer, $package, 'active');

        $this->assertTrue($file->fresh()->isVisibleTo($this->customer));
        $this->actingAs($this->customer)
            ->get(route('downloads.index'))
            ->assertSee($file->folder->name);
    }

    public function test_download_limit_is_enforced_per_user(): void
    {
        $file = $this->createFile([
            'allow_guests' => false,
            'download_limit' => 1,
        ]);

        $this->actingAs($this->customer)
            ->get(route('downloads.download', [$file->folder, $file]))
            ->assertOk();

        $this->actingAs($this->customer)
            ->get(route('downloads.download', [$file->folder, $file]))
            ->assertForbidden();

        $other = User::factory()->create(['status' => 'active']);

        $this->actingAs($other)
            ->get(route('downloads.download', [$file->folder, $file]))
            ->assertOk();
    }

    public function test_availability_window_blocks_early_and_expired_downloads(): void
    {
        $file = $this->createFile([
            'allow_guests' => true,
            'available_from' => now()->addDay(),
        ]);

        $this->assertSame(DownloadFile::DENIAL_UNAVAILABLE, $file->denialReason(null, '127.0.0.1'));

        $file->update([
            'available_from' => now()->subDay(),
            'available_until' => now()->subHour(),
        ]);

        $this->assertSame(DownloadFile::DENIAL_EXPIRED, $file->fresh()->denialReason(null, '127.0.0.1'));
    }

    public function test_staff_without_permission_cannot_create_folders(): void
    {
        $this->expectException(ValidationException::class);

        DownloadFolder::actions()->createAsAdmin([
            'admin_user_id' => $this->customer->id,
            'name' => 'Nope',
        ]);
    }

    public function test_folders_can_be_reordered(): void
    {
        $first = DownloadFolder::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'name' => 'First',
            'sort_order' => 10,
        ]);
        $second = DownloadFolder::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'name' => 'Second',
            'sort_order' => 20,
        ]);

        DownloadFolder::actions()->moveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'folder_id' => $second->id,
            'direction' => 'up',
        ]);

        $this->assertSame(['Second', 'First'], DownloadFolder::query()->ordered()->pluck('name')->all());
        $this->assertNotSame($first->fresh()->sort_order, $second->fresh()->sort_order);
    }

    public function test_deleting_a_folder_removes_stored_files(): void
    {
        $file = $this->createFile();
        $path = $file->path;
        $folderId = $file->folder_id;

        DownloadFolder::actions()->deleteAsAdmin([
            'admin_user_id' => $this->admin->id,
            'folder_id' => $folderId,
        ]);

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('download_folders', ['id' => $folderId]);
        $this->assertDatabaseMissing('download_files', ['id' => $file->id]);
    }

    public function test_client_index_lists_visible_folders(): void
    {
        $visible = $this->createFolder(['name' => 'Public extras']);
        $this->createFile([
            'folder' => $visible,
            'allow_guests' => true,
            'name' => 'Welcome pack',
        ]);

        $hidden = $this->createFolder(['name' => 'Staff only', 'is_visible' => false]);
        $this->createFile([
            'folder' => $hidden,
            'allow_guests' => true,
            'name' => 'Internal',
        ]);

        $this->get(route('downloads.index'))
            ->assertOk()
            ->assertSee('Public extras')
            ->assertDontSee('Staff only');
    }

    public function test_updating_file_keeps_existing_upload_when_no_replacement(): void
    {
        $file = $this->createFile(['name' => 'Old name']);
        $path = $file->path;

        $updated = DownloadFile::actions()->updateAsAdmin([
            'admin_user_id' => $this->admin->id,
            'file_id' => $file->id,
            'name' => 'New name',
            'description' => 'Updated notes',
        ]);

        $this->assertSame('New name', $updated->name);
        $this->assertSame('Updated notes', $updated->description);
        $this->assertSame($path, $updated->path);
    }

    protected function createFolder(array $overrides = []): DownloadFolder
    {
        $payload = [
            'admin_user_id' => $this->admin->id,
            'name' => $overrides['name'] ?? 'Game files',
            'description' => $overrides['description'] ?? null,
            'is_visible' => $overrides['is_visible'] ?? true,
        ];

        if (isset($overrides['sort_order'])) {
            $payload['sort_order'] = $overrides['sort_order'];
        }

        return DownloadFolder::actions()->createAsAdmin($payload);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createFile(array $overrides = []): DownloadFile
    {
        $folder = $overrides['folder'] ?? $this->createFolder();

        return DownloadFile::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'folder_id' => $folder->id,
            'name' => $overrides['name'] ?? 'Client files',
            'description' => $overrides['description'] ?? null,
            'version' => $overrides['version'] ?? null,
            'file' => UploadedFile::fake()->create($overrides['original'] ?? 'client.zip', 20, 'application/zip'),
            'is_published' => $overrides['is_published'] ?? true,
            'allow_guests' => $overrides['allow_guests'] ?? false,
            'require_any_order' => $overrides['require_any_order'] ?? false,
            'require_active_order' => $overrides['require_active_order'] ?? true,
            'hidden_until_eligible' => $overrides['hidden_until_eligible'] ?? false,
            'package_ids' => $overrides['package_ids'] ?? [],
            'download_limit' => $overrides['download_limit'] ?? null,
            'available_from' => $overrides['available_from'] ?? null,
            'available_until' => $overrides['available_until'] ?? null,
        ]);
    }

    protected function createPackage(): Package
    {
        $category = Category::query()->create([
            'name' => 'Hosting',
            'slug' => 'hosting-'.uniqid(),
            'icon' => 'server',
            'status' => 'active',
        ]);

        $connectionId = DB::table('server_connections')->insertGetId([
            'extension_identifier' => 'none',
            'alias' => 'conn-'.uniqid(),
            'status' => 'active',
            'is_active' => true,
            'receive_alerts' => false,
            'prevent_purchasing' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Package::query()->create([
            'category_id' => $category->id,
            'connection_id' => $connectionId,
            'slug' => 'pkg-'.uniqid(),
            'name' => 'VPS '.uniqid(),
            'status' => 'active',
        ]);
    }

    protected function createOrderFor(User $user, Package $package, string $status): Order
    {
        return Order::withoutEvents(fn () => Order::query()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => $status,
            'due_date' => now()->addMonth(),
        ]));
    }
}
