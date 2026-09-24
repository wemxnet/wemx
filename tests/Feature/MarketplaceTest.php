<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\User;
use Extensions\Modules\Marketplace\Enums\LicenseStatus;
use Extensions\Modules\Marketplace\Enums\ResourceStatus;
use Extensions\Modules\Marketplace\Enums\SaleStatus;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Enums\VersionStatus;
use Extensions\Modules\Marketplace\Models\MarketplaceCategory;
use Extensions\Modules\Marketplace\Models\MarketplaceCreatorGatewayConfig;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Models\MarketplaceResourceReview;
use Extensions\Modules\Marketplace\Models\MarketplaceResourceVersion;
use Extensions\Modules\Marketplace\Models\MarketplaceSale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $creator;

    protected User $buyer;

    protected MarketplaceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => 'extensions/Modules/Marketplace/Migrations',
        ]);

        if (! Route::has('api.marketplace.resources.index')) {
            require base_path('extensions/Modules/Marketplace/routes.php');
        }

        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['url']->setRoutes($this->app['router']->getRoutes());
        $this->app['view']->addNamespace('marketplace', base_path('extensions/Modules/Marketplace/Views'));
        $this->app['translator']->addNamespace('marketplace', base_path('extensions/Modules/Marketplace/Lang'));
        Volt::mount(base_path('extensions/Modules/Marketplace/Views'));

        config([
            'app.installed' => true,
            'app.license_key' => 'WMX-TESTING-KEY',
        ]);
        Cache::put('lcs_checked_at', now(), 21600);

        Storage::fake('local');
        Storage::fake('public');

        $this->admin = User::factory()->create();
        $this->creator = User::factory()->create();
        $this->buyer = User::factory()->create();
        $this->category = MarketplaceCategory::query()->where('slug', 'module')->firstOrFail();
    }

    public function test_creator_can_open_resource_studio_sections(): void
    {
        $resource = $this->createResource();

        $this->actingAs($this->creator)
            ->get('/marketplace/studio/resources/'.$resource->slug)
            ->assertOk()
            ->assertSee('Resource')
            ->assertSee('Versions')
            ->assertSee('Purchases')
            ->assertSee('Team');

        $this->actingAs($this->creator)->get('/marketplace/studio/resources/'.$resource->slug.'/versions')->assertOk();
        $this->actingAs($this->creator)->get('/marketplace/studio/resources/'.$resource->slug.'/licenses')->assertOk();
        $this->actingAs($this->creator)->get('/marketplace/studio/resources/'.$resource->slug.'/team')->assertOk();

        $this->actingAs($this->buyer)->get('/marketplace/studio/resources/'.$resource->slug)->assertForbidden();
    }

    public function test_seeded_categories_exist(): void
    {
        $this->assertTrue(MarketplaceCategory::query()->where('slug', 'server')->exists());
        $this->assertTrue(MarketplaceCategory::query()->where('slug', 'payment-gateway')->exists());
        $this->assertTrue(MarketplaceCategory::query()->where('slug', 'client-theme')->exists());
        $this->assertSame(8, MarketplaceCategory::query()->count());
    }

    public function test_creator_can_publish_a_resource_and_version(): void
    {
        $resource = $this->createResource();
        $version = $this->createVersion($resource);

        $this->assertSame(ResourceStatus::Pending, $resource->status);
        $this->assertSame(VersionStatus::Pending, $version->status);
        $this->assertSame($this->creator->id, $resource->user_id);
        $this->assertTrue($resource->available_on_integrated_marketplace);
        $this->assertSame(TeamRole::Owner, $resource->teamRoleFor($this->creator));
        $this->assertSame('1.0.0', $version->version);
        $this->assertSame('extensions/Modules', $version->extract_path);
        $this->assertTrue(Storage::disk('local')->exists($version->path));
    }

    public function test_versions_auto_approve_when_resource_is_approved(): void
    {
        $resource = $this->createResource();
        $pendingVersion = $this->createVersion($resource, ['version' => '1.0.0']);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        $this->assertSame(VersionStatus::Approved, $pendingVersion->fresh()->status);

        $liveVersion = $this->createVersion($resource, [
            'name' => 'Hotfix',
            'version' => '1.0.1',
            'notify_customers' => true,
        ]);

        $this->assertSame(VersionStatus::Approved, $liveVersion->status);
    }

    public function test_versions_are_ordered_by_created_at_and_can_be_deleted_when_not_the_last(): void
    {
        $resource = $this->createResource();
        $first = $this->createVersion($resource, ['version' => '1.0.0']);
        $first->forceFill(['created_at' => now()->subMinute()])->save();
        $second = $this->createVersion($resource, ['version' => '2.0.0', 'name' => 'Second']);

        $ordered = $resource->fresh()->versions()->pluck('version')->all();
        $this->assertSame(['2.0.0', '1.0.0'], $ordered);

        MarketplaceResourceVersion::actions()->deleteAsCreator([
            'user_id' => $this->creator->id,
            'version_id' => $first->id,
        ]);

        $this->assertFalse(MarketplaceResourceVersion::query()->whereKey($first->id)->exists());
        $this->assertTrue(MarketplaceResourceVersion::query()->whereKey($second->id)->exists());

        $this->expectException(ValidationException::class);

        MarketplaceResourceVersion::actions()->deleteAsCreator([
            'user_id' => $this->creator->id,
            'version_id' => $second->id,
        ]);
    }

    public function test_author_can_grant_and_revoke_purchase_access(): void
    {
        $resource = $this->createResource(['name' => 'Paid access']);
        $this->createVersion($resource);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        $license = MarketplaceLicense::actions()->grantAsManager([
            'actor_user_id' => $this->creator->id,
            'resource_id' => $resource->id,
            'username' => $this->buyer->email,
            'payment_method' => 'bank_transfer',
            'transaction_id' => 'WIRE-123',
            'notify' => false,
        ]);

        $this->assertSame(LicenseStatus::Active, $license->status);
        $this->assertSame('manual', $license->source);
        $this->assertSame('bank_transfer', $license->payment_method);
        $this->assertSame('WIRE-123', $license->transaction_id);
        $this->assertSame(1, $resource->fresh()->purchases_count);

        MarketplaceLicense::actions()->revoke([
            'actor_user_id' => $this->creator->id,
            'license_id' => $license->id,
        ]);

        $this->assertSame(LicenseStatus::Revoked, $license->fresh()->status);
    }

    public function test_version_release_can_notify_purchasers(): void
    {
        $resource = $this->createResource(['name' => 'Notify releases']);
        $this->createVersion($resource, ['version' => '1.0.0']);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        MarketplaceLicense::actions()->grantAsManager([
            'actor_user_id' => $this->creator->id,
            'resource_id' => $resource->id,
            'user_id' => $this->buyer->id,
            'payment_method' => 'manual',
            'notify' => false,
        ]);

        $version = $this->createVersion($resource, [
            'name' => 'Update',
            'version' => '1.1.0',
            'changelog' => 'Bug fixes and improvements.',
            'notify_customers' => true,
        ]);

        $this->assertDatabaseHas('emails', [
            'to' => $this->buyer->email,
            'identifier' => 'marketplace.version.released.'.$version->id.'.'.$this->buyer->id,
        ]);
    }

    public function test_pending_resources_are_hidden_from_the_public(): void
    {
        $resource = $this->createResource();

        $this->assertFalse($resource->isVisibleTo(null));
        $this->assertFalse($resource->isVisibleTo($this->buyer));
        $this->assertTrue($resource->isVisibleTo($this->creator));
        $this->assertTrue($resource->isVisibleTo($this->admin));
        $this->assertFalse(
            MarketplaceResource::query()->visibleTo($this->buyer)->whereKey($resource->id)->exists()
        );
    }

    public function test_admin_can_approve_feature_and_sort_by_popularity(): void
    {
        $quiet = $this->createResource(['name' => 'Quiet module']);
        $popular = $this->createResource(['name' => 'Popular module']);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $quiet->id,
        ]);
        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $popular->id,
        ]);

        $quiet->update(['views_count' => 2, 'downloads_count' => 1]);
        $popular->update(['views_count' => 20, 'downloads_count' => 8]);

        MarketplaceResource::actions()->featureAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $quiet->id,
            'is_featured' => true,
        ]);

        $ordered = MarketplaceResource::query()->approved()->popular()->pluck('name')->all();

        $this->assertSame(['Quiet module', 'Popular module'], $ordered);
        $this->assertTrue($quiet->fresh()->isFeaturedNow());
    }

    public function test_customer_cannot_manage_someone_elses_resource(): void
    {
        $resource = $this->createResource();

        $this->expectException(ValidationException::class);

        MarketplaceResource::actions()->updateAsCreator([
            'user_id' => $this->buyer->id,
            'resource_id' => $resource->id,
            'name' => 'Hijacked',
        ]);
    }

    public function test_admin_can_add_a_team_member(): void
    {
        $resource = $this->createResource();

        $member = MarketplaceResource::actions()->addTeamMember([
            'actor_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
            'user_id' => $this->buyer->id,
        ]);

        $this->assertSame(TeamRole::Manager, $member->role);
        $this->assertTrue($resource->fresh()->userCan($this->buyer, TeamRole::Manager));
    }

    public function test_creator_gateway_credentials_are_encrypted(): void
    {
        $config = MarketplaceCreatorGatewayConfig::actions()->create([
            'user_id' => $this->creator->id,
            'name' => 'Stripe live',
            'driver' => 'stripe',
            'credentials' => [
                'secret_key' => 'sk_test_secret_value',
                'publishable_key' => 'pk_test_public_value',
                'webhook_secret' => 'whsec_test_secret',
            ],
        ]);

        $raw = DB::table('marketplace_creator_gateway_configs')
            ->where('id', $config->id)
            ->value('credentials');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('sk_test_secret_value', $raw);
        $this->assertSame('sk_test_secret_value', $config->fresh()->credential('secret_key'));
    }

    public function test_paid_checkout_requires_a_gateway_and_completing_it_issues_a_license(): void
    {
        $resource = $this->createResource(['name' => 'Paid module', 'price' => 12.5]);
        $this->createVersion($resource);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        try {
            MarketplaceSale::actions()->startCheckout([
                'user_id' => $this->buyer->id,
                'resource_id' => $resource->id,
            ]);
            $this->fail('Checkout should require a payment method.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('resource_id', $exception->errors());
        }

        $config = MarketplaceCreatorGatewayConfig::actions()->create([
            'user_id' => $this->creator->id,
            'name' => 'PayPal',
            'driver' => 'paypal_ipn',
            'credentials' => [
                'email' => 'seller@example.com',
                'mode' => 'sandbox',
            ],
        ]);

        MarketplaceResource::actions()->attachGateway([
            'user_id' => $this->creator->id,
            'resource_id' => $resource->id,
            'gateway_config_id' => $config->id,
        ]);

        $sale = MarketplaceSale::actions()->startCheckout([
            'user_id' => $this->buyer->id,
            'resource_id' => $resource->id,
        ]);

        $this->assertSame(SaleStatus::Pending, $sale->status);
        $this->assertSame($config->id, $sale->gateway_config_id);

        $stripe = MarketplaceCreatorGatewayConfig::actions()->create([
            'user_id' => $this->creator->id,
            'name' => 'Stripe',
            'driver' => 'stripe',
            'credentials' => [
                'secret_key' => 'sk_test_secret_value',
                'publishable_key' => 'pk_test_public_value',
                'webhook_secret' => 'whsec_test_secret',
            ],
        ]);

        MarketplaceResource::actions()->syncGateways([
            'user_id' => $this->creator->id,
            'resource_id' => $resource->id,
            'gateway_config_ids' => [$config->id, $stripe->id],
        ]);

        $this->assertSame(
            [$config->id, $stripe->id],
            $resource->fresh()->gatewayConfigs()->orderBy('marketplace_creator_gateway_configs.id')->pluck('marketplace_creator_gateway_configs.id')->all()
        );

        try {
            MarketplaceSale::actions()->startCheckout([
                'user_id' => $this->buyer->id,
                'resource_id' => $resource->id,
            ]);
            $this->fail('Checkout with multiple methods should require an explicit gateway.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('gateway_config_id', $exception->errors());
        }

        $sale = MarketplaceSale::actions()->startCheckout([
            'user_id' => $this->buyer->id,
            'resource_id' => $resource->id,
            'gateway_config_id' => $stripe->id,
        ]);

        $this->assertSame($stripe->id, $sale->gateway_config_id);
        $this->assertSame('stripe', $sale->driver);

        MarketplaceSale::actions()->complete($sale, [
            'gateway_reference' => 'TXN-1',
        ]);

        $sale->refresh();
        $this->assertTrue($sale->isCompleted());
        $this->assertNotNull($sale->license);
        $this->assertSame(LicenseStatus::Active, $sale->license->status);
        $this->assertSame(1, $resource->fresh()->purchases_count);
    }

    public function test_free_download_grants_a_license_and_records_a_download(): void
    {
        $resource = $this->createResource();
        $version = $this->createVersion($resource);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        $this->assertSame(VersionStatus::Approved, $version->fresh()->status);

        MarketplaceResourceVersion::actions()->downloadForUser([
            'version_id' => $version->id,
            'user_id' => $this->buyer->id,
        ]);

        $this->assertTrue(
            MarketplaceLicense::query()
                ->where('resource_id', $resource->id)
                ->where('user_id', $this->buyer->id)
                ->exists()
        );
        $this->assertSame(1, $resource->fresh()->downloads_count);
        $this->assertSame(1, $version->fresh()->downloads_count);
    }

    public function test_licensed_user_can_download_a_specific_older_version(): void
    {
        $resource = $this->createResource(['price' => 12]);
        $older = $this->createVersion($resource, ['version' => '1.0.0', 'name' => 'Initial']);
        $older->forceFill(['created_at' => now()->subMinute()])->save();
        $latest = $this->createVersion($resource, ['version' => '1.1.0', 'name' => 'Update']);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        MarketplaceLicense::actions()->grantAsManager([
            'actor_user_id' => $this->creator->id,
            'resource_id' => $resource->id,
            'user_id' => $this->buyer->id,
            'notify' => false,
        ]);

        $this->assertTrue($older->fresh()->isDownloadable($resource));
        $this->assertTrue($latest->fresh()->isDownloadable($resource));

        MarketplaceResourceVersion::actions()->downloadForUser([
            'version_id' => $older->id,
            'user_id' => $this->buyer->id,
        ]);

        $this->assertSame(1, $older->fresh()->downloads_count);
        $this->assertSame(0, $latest->fresh()->downloads_count);
        $this->assertSame(1, $resource->fresh()->downloads_count);
    }

    public function test_admin_can_grant_purchase_access_to_a_resource(): void
    {
        $resource = $this->createResource(['price' => 15]);
        $this->createVersion($resource);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        $license = MarketplaceLicense::actions()->grantAsManager([
            'actor_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
            'username' => $this->buyer->email,
            'payment_method' => 'manual',
            'notify' => false,
        ]);

        $this->assertSame(LicenseStatus::Active, $license->status);
        $this->assertSame($this->buyer->id, $license->user_id);
        $this->assertSame('manual', $license->source);
        $this->assertSame(1, $resource->fresh()->purchases_count);

        $results = MarketplaceLicense::query()
            ->where('resource_id', $resource->id)
            ->search($this->buyer->username)
            ->get();

        $this->assertTrue($results->contains('id', $license->id));
    }

    public function test_integrated_api_only_lists_approved_integrated_resources(): void
    {
        $hidden = $this->createResource(['name' => 'Hidden']);
        $listed = $this->createResource(['name' => 'Listed module']);
        $this->createVersion($listed);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $listed->id,
        ]);

        $response = $this->getJson('/api/v1/marketplace/resources');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Listed module'));
        $this->assertFalse($names->contains('Hidden'));
        $this->assertSame('Free', $response->json('data.0.price'));
        $this->assertNotEmpty($response->json('data.0.latest_version'));
        $this->assertArrayNotHasKey('description', $response->json('data.0'));
        $this->assertArrayNotHasKey('versions', $response->json('data.0'));
        $this->assertSame(18, $response->json('per_page'));
        $this->assertNotEmpty($response->json('categories'));
    }

    public function test_integrated_api_show_includes_versions_and_reviews(): void
    {
        $resource = $this->createResource(['name' => 'Detailed module']);
        $this->createVersion($resource);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        MarketplaceResourceReview::actions()->upsertAsClient([
            'user_id' => $this->buyer->id,
            'resource_id' => $resource->id,
            'rating' => 5,
            'title' => 'Great free tool',
            'body' => 'Works well for my client dashboard.',
        ]);

        $response = $this->getJson('/api/v1/marketplace/resources/'.$resource->slug);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Detailed module')
            ->assertJsonPath('data.reviews_count', 1)
            ->assertJsonPath('data.reviews.0.title', 'Great free tool')
            ->assertJsonPath('data.versions.0.version', '1.0.0');

        $this->assertNotEmpty($response->json('data.view_url'));
    }

    public function test_integrated_paid_download_requires_a_license_key(): void
    {
        $resource = $this->createResource(['price' => 9]);
        $version = $this->createVersion($resource);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        $this->expectException(ValidationException::class);

        MarketplaceResourceVersion::actions()->downloadForIntegrated([
            'version_id' => $version->id,
            'license_key' => 'invalid',
        ]);
    }

    public function test_extract_path_cannot_escape_the_app(): void
    {
        $resource = $this->createResource();

        $this->expectException(ValidationException::class);

        MarketplaceResourceVersion::actions()->createAsCreator([
            'user_id' => $this->creator->id,
            'resource_id' => $resource->id,
            'name' => 'Bad path',
            'version' => '0.0.1',
            'wemx_version' => '*',
            'available_on_integrated_marketplace' => true,
            'extract_path' => '../etc',
            'file' => UploadedFile::fake()->create('bad.zip', 20, 'application/zip'),
        ]);
    }

    public function test_resource_initials_placeholder_uses_first_letters(): void
    {
        $resource = $this->createResource(['name' => 'Proxmox Server']);

        $this->assertSame('PS', $resource->initials());
        $this->assertNull($resource->iconUrl());
    }

    public function test_marketplace_emails_are_sent_for_lifecycle_events(): void
    {
        $resource = $this->createResource(['name' => 'Notify Module']);

        $this->assertDatabaseHas('emails', [
            'to' => $this->creator->email,
            'identifier' => 'marketplace.resource.pending.'.$resource->id.'.'.$this->creator->id,
        ]);

        $this->assertStringContainsString(
            'pending review',
            strtolower((string) Email::query()->where('identifier', 'marketplace.resource.pending.'.$resource->id.'.'.$this->creator->id)->value('subject'))
        );

        $version = $this->createVersion($resource);

        $this->assertDatabaseHas('emails', [
            'to' => $this->creator->email,
            'identifier' => 'marketplace.version.submitted.'.$resource->id.'.'.$this->creator->id.'.'.$version->id,
        ]);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        $this->assertDatabaseHas('emails', [
            'to' => $this->creator->email,
            'identifier' => 'marketplace.resource.approved.'.$resource->id.'.'.$this->creator->id,
        ]);

        MarketplaceResource::actions()->rejectAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
            'rejection_reason' => 'Missing documentation.',
        ]);

        $rejected = Email::query()
            ->where('identifier', 'marketplace.resource.rejected.'.$resource->id.'.'.$this->creator->id)
            ->first();

        $this->assertNotNull($rejected);
        $this->assertStringContainsString('Missing documentation.', implode("\n", $rejected->lines ?? []));

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        MarketplaceResource::actions()->suspendAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
            'rejection_reason' => 'Policy violation.',
        ]);

        $this->assertDatabaseHas('emails', [
            'to' => $this->creator->email,
            'identifier' => 'marketplace.resource.suspended.'.$resource->id.'.'.$this->creator->id,
        ]);
    }

    public function test_purchase_emails_notify_buyer_and_seller(): void
    {
        $resource = $this->createResource(['name' => 'Paid notify', 'price' => 9.99]);
        $this->createVersion($resource);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        $config = MarketplaceCreatorGatewayConfig::actions()->create([
            'user_id' => $this->creator->id,
            'name' => 'PayPal',
            'driver' => 'paypal_ipn',
            'credentials' => [
                'email' => 'seller@example.com',
                'mode' => 'sandbox',
            ],
        ]);

        MarketplaceResource::actions()->attachGateway([
            'user_id' => $this->creator->id,
            'resource_id' => $resource->id,
            'gateway_config_id' => $config->id,
        ]);

        $sale = MarketplaceSale::actions()->startCheckout([
            'user_id' => $this->buyer->id,
            'resource_id' => $resource->id,
        ]);

        MarketplaceSale::actions()->complete($sale, [
            'gateway_reference' => 'TXN-EMAIL-1',
        ]);

        $this->assertDatabaseHas('emails', [
            'to' => $this->buyer->email,
            'identifier' => 'marketplace.sale.'.$sale->id.'.buyer',
        ]);

        $this->assertDatabaseHas('emails', [
            'to' => $this->creator->email,
            'identifier' => 'marketplace.sale.'.$sale->id.'.seller',
        ]);
    }

    public function test_stripe_checkout_stores_the_session_id(): void
    {
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            ], 200),
        ]);

        $resource = $this->createResource(['price' => 5]);
        $this->createVersion($resource);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        $config = MarketplaceCreatorGatewayConfig::actions()->create([
            'user_id' => $this->creator->id,
            'name' => 'Stripe',
            'driver' => 'stripe',
            'credentials' => [
                'secret_key' => 'sk_test_123',
                'publishable_key' => 'pk_test_123',
                'webhook_secret' => 'whsec_123',
            ],
        ]);

        MarketplaceResource::actions()->attachGateway([
            'user_id' => $this->creator->id,
            'resource_id' => $resource->id,
            'gateway_config_id' => $config->id,
        ]);

        $sale = MarketplaceSale::actions()->startCheckout([
            'user_id' => $this->buyer->id,
            'resource_id' => $resource->id,
        ]);

        $response = $config->driver()->checkout($sale, $config);

        $this->assertTrue($response->isRedirect());
        $this->assertSame('cs_test_123', $sale->fresh()->gateway_reference);
    }

    public function test_version_numbers_must_be_semantic(): void
    {
        $resource = $this->createResource();

        try {
            $this->createVersion($resource, ['version' => 'beta-0.1.1']);
            $this->fail('Non-semantic versions should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('version', $exception->errors());
        }

        $version = $this->createVersion($resource, ['version' => '1.2.3-beta.1']);
        $this->assertSame('1.2.3-beta.1', $version->version);
    }

    public function test_buyers_can_review_paid_resources_and_anyone_can_review_free_ones(): void
    {
        $free = $this->createResource(['name' => 'Free reviewable', 'price' => 0]);
        $this->createVersion($free);
        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $free->id,
        ]);

        $freeReview = MarketplaceResourceReview::actions()->upsertAsClient([
            'user_id' => $this->buyer->id,
            'resource_id' => $free->id,
            'rating' => 5,
            'title' => 'Great free tool',
            'body' => 'Works well for my client dashboard.',
        ]);

        $this->assertSame(5, $freeReview->rating);
        $this->assertSame(1, $free->fresh()->reviews_count);
        $this->assertEqualsWithDelta(5.0, (float) $free->fresh()->reviews_avg, 0.01);

        $paid = $this->createResource(['name' => 'Paid reviewable', 'price' => 12]);
        $this->createVersion($paid, ['version' => '1.0.1']);
        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $paid->id,
        ]);

        try {
            MarketplaceResourceReview::actions()->upsertAsClient([
                'user_id' => $this->buyer->id,
                'resource_id' => $paid->id,
                'rating' => 4,
                'body' => 'I have not purchased this yet.',
            ]);
            $this->fail('Non-buyers should not review paid resources.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('resource_id', $exception->errors());
        }

        MarketplaceLicense::actions()->grantAsManager([
            'actor_user_id' => $this->creator->id,
            'resource_id' => $paid->id,
            'user_id' => $this->buyer->id,
            'payment_method' => 'manual',
            'notify' => false,
        ]);

        $paidReview = MarketplaceResourceReview::actions()->upsertAsClient([
            'user_id' => $this->buyer->id,
            'resource_id' => $paid->id,
            'rating' => 4,
            'body' => 'Solid paid resource after buying it.',
        ]);

        $this->assertSame(4, $paidReview->rating);
        $this->assertTrue($paid->fresh()->canBeReviewedBy($this->buyer));
    }

    public function test_library_pages_are_available_to_authenticated_clients(): void
    {
        $this->actingAs($this->buyer)->get('/marketplace/library/purchases')->assertOk()->assertSee('My Purchases');
        $this->actingAs($this->creator)->get('/marketplace/library/resources')->assertOk()->assertSee('My Resources');
        auth()->logout();
        $this->get('/marketplace/library/purchases')->assertRedirect();
    }

    public function test_admin_can_mark_a_resource_official(): void
    {
        $resource = $this->createResource();
        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        MarketplaceResource::actions()->setOfficialAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
            'is_official' => true,
        ]);

        $this->assertTrue($resource->fresh()->is_official);
    }

    public function test_author_can_disable_a_resource_while_buyers_keep_access(): void
    {
        $resource = $this->createResource(['name' => 'Paid disable', 'price' => 10]);
        $this->createVersion($resource);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        MarketplaceLicense::actions()->grantAsManager([
            'actor_user_id' => $this->creator->id,
            'resource_id' => $resource->id,
            'username' => $this->buyer->email,
            'notify' => false,
        ]);

        MarketplaceResource::actions()->setDisabledAsCreator([
            'user_id' => $this->creator->id,
            'resource_id' => $resource->id,
            'is_disabled' => true,
        ]);

        $resource->refresh();

        $this->assertTrue($resource->is_disabled);
        $this->assertFalse($resource->isListedPublicly());
        $this->assertFalse($resource->isVisibleTo(null));
        $this->assertFalse(
            MarketplaceResource::query()->visibleTo($this->buyer)->whereKey($resource->id)->exists()
        );
        $this->assertTrue($resource->isVisibleTo($this->buyer));
        $this->assertTrue($resource->isVisibleTo($this->creator));
    }

    public function test_paid_resources_with_purchases_cannot_be_deleted(): void
    {
        $resource = $this->createResource(['name' => 'Paid lock', 'price' => 15]);
        $this->createVersion($resource);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $resource->id,
        ]);

        $config = MarketplaceCreatorGatewayConfig::actions()->create([
            'user_id' => $this->creator->id,
            'name' => 'PayPal',
            'driver' => 'paypal_ipn',
            'credentials' => [
                'email' => 'seller@example.com',
                'mode' => 'sandbox',
            ],
        ]);

        MarketplaceResource::actions()->attachGateway([
            'user_id' => $this->creator->id,
            'resource_id' => $resource->id,
            'gateway_config_id' => $config->id,
        ]);

        $sale = MarketplaceSale::actions()->startCheckout([
            'user_id' => $this->buyer->id,
            'resource_id' => $resource->id,
        ]);

        MarketplaceSale::actions()->complete($sale, ['gateway_reference' => 'TXN-LOCK']);

        $this->assertFalse($resource->fresh()->canBeDeleted());

        try {
            MarketplaceResource::actions()->deleteAsCreator([
                'user_id' => $this->creator->id,
                'resource_id' => $resource->id,
            ]);
            $this->fail('Paid resources with purchases should not be deletable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('resource_id', $exception->errors());
        }

        try {
            MarketplaceResource::actions()->deleteAsAdmin([
                'admin_user_id' => $this->admin->id,
                'resource_id' => $resource->id,
            ]);
            $this->fail('Admins should also be blocked from deleting paid resources with purchases.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('resource_id', $exception->errors());
        }
    }

    public function test_authors_can_delete_free_or_unpurchased_paid_resources(): void
    {
        $free = $this->createResource(['name' => 'Free delete']);
        $this->createVersion($free);

        $this->assertTrue(
            MarketplaceResource::actions()->deleteAsCreator([
                'user_id' => $this->creator->id,
                'resource_id' => $free->id,
            ])
        );
        $this->assertDatabaseMissing('marketplace_resources', ['id' => $free->id]);

        $paid = $this->createResource(['name' => 'Paid unused', 'price' => 9]);
        $this->createVersion($paid);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $paid->id,
        ]);

        $this->assertTrue($paid->fresh()->canBeDeleted());
        $this->assertTrue(
            MarketplaceResource::actions()->deleteAsAdmin([
                'admin_user_id' => $this->admin->id,
                'resource_id' => $paid->id,
            ])
        );
        $this->assertDatabaseMissing('marketplace_resources', ['id' => $paid->id]);
    }

    public function test_author_profile_lists_authored_and_collaborated_resources(): void
    {
        $owned = $this->createResource(['name' => 'Owned listing']);
        $collab = $this->createResource(['name' => 'Collab listing', 'user_id' => $this->buyer->id]);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $owned->id,
        ]);
        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $collab->id,
        ]);

        MarketplaceResource::actions()->addTeamMember([
            'actor_user_id' => $this->buyer->id,
            'resource_id' => $collab->id,
            'user_id' => $this->creator->id,
        ]);

        $this->get('/marketplace/authors/'.$this->creator->username)
            ->assertOk()
            ->assertSee('Owned listing')
            ->assertSee('Authored');

        $this->get('/marketplace/authors/nobody-here-'.uniqid())
            ->assertNotFound();
    }

    public function test_browse_sort_supports_downloads_and_free_popular(): void
    {
        $free = $this->createResource(['name' => 'Free popular', 'price' => 0]);
        $paid = $this->createResource(['name' => 'Paid popular', 'price' => 8]);

        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $free->id,
        ]);
        MarketplaceResource::actions()->approveAsAdmin([
            'admin_user_id' => $this->admin->id,
            'resource_id' => $paid->id,
        ]);

        $free->update(['downloads_count' => 5, 'purchases_count' => 0]);
        $paid->update(['downloads_count' => 20, 'purchases_count' => 4]);

        $byDownloads = MarketplaceResource::query()->approved()->sortedBy('downloads')->pluck('name')->all();
        $this->assertSame(['Paid popular', 'Free popular'], $byDownloads);

        $freeOnly = MarketplaceResource::query()->approved()->sortedBy('popular_free')->pluck('name')->all();
        $this->assertSame(['Free popular'], $freeOnly);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createResource(array $overrides = []): MarketplaceResource
    {
        return MarketplaceResource::actions()->createAsCreator(array_merge([
            'user_id' => $this->creator->id,
            'category_id' => $this->category->id,
            'name' => 'Demo module',
            'short_description' => 'Adds extra client tools.',
            'description' => "## Overview\n\nA **demo** resource.",
            'price' => 0,
            'website_url' => 'https://example.com',
            'source_url' => 'https://github.com/example/demo',
            'available_on_integrated_marketplace' => true,
        ], $overrides));
    }

    protected function createVersion(MarketplaceResource $resource, array $overrides = []): MarketplaceResourceVersion
    {
        return MarketplaceResourceVersion::actions()->createAsCreator(array_merge([
            'user_id' => $this->creator->id,
            'resource_id' => $resource->id,
            'name' => 'Initial release',
            'version' => '1.0.0',
            'wemx_version' => '*',
            'changelog' => 'First public build.',
            'available_on_integrated_marketplace' => true,
            'notify_customers' => false,
            'extract_path' => 'extensions/Modules',
            'file' => UploadedFile::fake()->create('demo.zip', 40, 'application/zip'),
        ], $overrides));
    }
}
