<?php

namespace Tests\Feature;

use App\Http\Middleware\InstallAppMiddleware;
use App\Http\Middleware\SyncRuntimeMiddleware;
use App\Models\Category;
use App\Models\EmailTemplate;
use App\Models\Extension;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\ServerConnection;
use App\Models\User;
use Extensions\Servers\Plesk\Providers\PleskServiceProvider;
use Extensions\Servers\Plesk\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PleskServerTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $stranger;

    protected ServerConnection $connection;

    protected Package $package;

    protected bool $customerExists = false;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Cache::flush();
        Http::preventStrayRequests();

        $this->app->register(PleskServiceProvider::class);
        $this->app['view']->addNamespace('server-plesk', base_path('extensions/Servers/Plesk/Views'));
        $this->app['translator']->addNamespace('server-plesk', base_path('extensions/Servers/Plesk/Lang'));
        Volt::mount(base_path('extensions/Servers/Plesk/Views'));

        if (! Route::has('plesk.login')) {
            require base_path('extensions/Servers/Plesk/routes.php');
            $this->app['router']->getRoutes()->refreshNameLookups();
        }

        $this->customer = User::factory()->create(['status' => 'active', 'email' => 'jane.doe@example.com']);
        $this->stranger = User::factory()->create(['status' => 'active']);

        Extension::query()->updateOrCreate(
            ['identifier' => 'server-plesk'],
            ['namespace' => Server::class, 'type' => 'server', 'name' => 'Plesk', 'status' => 'enabled', 'version' => '1.0.0']
        );

        $this->connection = ServerConnection::query()->create([
            'alias' => 'plesk-lab',
            'extension_identifier' => 'server-plesk',
            'status' => 'healthy',
            'is_active' => true,
            'prevent_purchasing' => false,
            'receive_alerts' => false,
            'config' => $this->credentials(),
        ]);

        $category = Category::query()->create([
            'name' => 'Web hosting',
            'slug' => 'web-hosting-'.uniqid(),
            'icon' => 'server',
            'status' => 'active',
        ]);

        $this->package = Package::query()->create([
            'category_id' => $category->id,
            'connection_id' => $this->connection->id,
            'slug' => 'plesk-basic-'.uniqid(),
            'name' => 'Plesk Basic '.uniqid(),
            'status' => 'active',
            'data' => [
                'plan' => 'Default Domain',
                'allow_login' => '1',
                'allow_password_change' => '1',
            ],
        ]);
    }

    public function test_connection_test_reads_the_server(): void
    {
        $this->fakePlesk();

        $this->assertSame('Connected to Plesk Obsidian 18.0.70.', Server::testConnection($this->credentials()));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://plesk.example.com:8443/api/v2/server'
            && $request->hasHeader('X-API-Key', 'APIKEY'));
    }

    public function test_basic_auth_is_used_without_an_api_key(): void
    {
        $this->fakePlesk();

        Server::testConnection(array_merge($this->credentials(), ['api_key' => '']));

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('admin:secret')));
    }

    public function test_rejected_credentials_are_explained(): void
    {
        Http::fake(fn () => Http::response(['code' => 1, 'message' => 'Unauthorized'], 401));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('rejected the credentials');

        Server::testConnection($this->credentials());
    }

    public function test_package_config_lists_service_plans(): void
    {
        $this->fakePlesk();

        $fields = collect((new Server)->setPackageConfig($this->package, $this->connection))->keyBy('key');

        $this->assertSame(['Default Domain' => 'Default Domain', 'Unlimited' => 'Unlimited'], $fields['plan']['options']);
    }

    public function test_package_config_falls_back_to_text_when_offline(): void
    {
        Http::fake(fn () => Http::response('', 500));

        $fields = collect((new Server)->setPackageConfig($this->package, $this->connection))->keyBy('key');

        $this->assertSame('text', $fields['plan']['type']);
    }

    public function test_create_makes_a_customer_and_subscription(): void
    {
        $this->fakePlesk();

        $order = $this->createOrder();

        (new Server)->create($order, $this->connection);

        $order->refresh();

        $this->assertSame('15', $order->external_id);
        $this->assertSame('example.com', $order->data['domain']);
        $this->assertSame('Default Domain', $order->data['plan']);
        $this->assertArrayNotHasKey('ftp_password', $order->data);
        $this->assertArrayNotHasKey('client_password', $order->data);
        $this->assertSame('janedoe'.$this->customer->id, $order->getExternalUser()->username);
        $this->assertNotSame('unknown', $order->getExternalUser()->password);
        $this->assertDatabaseHas('emails', ['user_id' => $this->customer->id, 'identifier' => 'server.plesk.created']);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/api/v2/clients')
            && $request['external_id'] === 'wemx-user-'.$this->customer->id
            && $request['type'] === 'customer');
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/api/v2/domains')
            && $request['name'] === 'example.com'
            && $request['owner_client']['id'] === 9
            && $request['plan']['name'] === 'Default Domain');
    }

    public function test_create_reuses_the_customer(): void
    {
        $this->customerExists = true;
        $this->fakePlesk();

        (new Server)->create($this->createOrder(), $this->connection);

        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/api/v2/clients'));
    }

    public function test_create_records_plesk_errors(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/api/v2/domains')) {
                return Http::response(['code' => 0, 'message' => 'Domain already exists'], 409);
            }

            return $this->responseFor($request);
        });

        $order = $this->createOrder();

        try {
            (new Server)->create($order, $this->connection);
            $this->fail('Expected create to fail.');
        } catch (\Exception $exception) {
            $this->assertSame('Domain already exists', $exception->getMessage());
        }

        $this->assertSame('Domain already exists', $order->fresh()->data['last_error']);
    }

    public function test_suspend_unsuspend_and_terminate(): void
    {
        $this->fakePlesk();

        $order = $this->provisionedOrder();
        $server = new Server;

        $server->suspend($order, $this->connection);
        $server->unsuspend($order, $this->connection);
        $server->terminate($order, $this->connection);

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && str_ends_with($request->url(), '/domains/15/status') && $request['status'] === 'suspended');
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && str_ends_with($request->url(), '/domains/15/status') && $request['status'] === 'active');
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/domains/15'));
    }

    public function test_upgrade_switches_the_service_plan(): void
    {
        $this->fakePlesk();

        $newPackage = $this->package->replicate();
        $newPackage->slug = 'plesk-unlimited-'.uniqid();
        $newPackage->name = 'Plesk Unlimited '.uniqid();
        $newPackage->data = array_merge($this->package->data ?? [], ['plan' => 'Unlimited']);
        $newPackage->save();

        $order = $this->provisionedOrder();

        (new Server)->upgradeOrDowngrade(
            $order,
            PackagePrice::query()->create(['package_id' => $this->package->id, 'period_in_days' => 30, 'price' => 10]),
            PackagePrice::query()->create(['package_id' => $newPackage->id, 'period_in_days' => 30, 'price' => 20]),
            $this->connection,
        );

        $this->assertSame('Unlimited', $order->fresh()->data['plan']);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/cli/subscription/call')
            && $request['params'] === ['--switch-subscription', 'example.com', '-service-plan', 'Unlimited']);
    }

    public function test_cli_failures_are_surfaced(): void
    {
        Http::fake(fn (Request $request) => str_contains($request->url(), '/cli/')
            ? Http::response(['code' => 1, 'stdout' => '', 'stderr' => 'Service plan not found'], 200)
            : $this->responseFor($request));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Service plan not found');

        Server::actions()->loginAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
        ]);
    }

    public function test_clients_get_a_one_time_login_link(): void
    {
        $this->fakePlesk();

        $order = $this->provisionedOrder();

        $this->withoutMiddleware([InstallAppMiddleware::class, SyncRuntimeMiddleware::class])
            ->actingAs($this->customer)
            ->get(route('plesk.login', $order))
            ->assertRedirect('https://plesk.example.com:8443/login?secret=abc');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/cli/admin/call')
            && $request['params'] === ['--get-login-link', '-user', 'janedoe1']);
    }

    public function test_strangers_cannot_log_in(): void
    {
        $this->fakePlesk();

        $this->expectException(ValidationException::class);

        Server::actions()->loginAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->stranger->id,
        ]);
    }

    public function test_clients_can_change_the_password(): void
    {
        $this->fakePlesk();

        $order = $this->provisionedOrder();

        Server::actions()->changePasswordAsClient([
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'password' => 'NewPleskPass1',
        ]);

        $this->assertSame('NewPleskPass1', $order->getExternalUser()->fresh()->password);
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && str_ends_with($request->url(), '/clients/9') && $request['password'] === 'NewPleskPass1');
    }

    public function test_password_change_is_blocked_when_disabled(): void
    {
        $this->fakePlesk();

        $this->package->update(['data' => array_merge($this->package->data ?? [], ['allow_password_change' => '0'])]);

        $this->expectException(ValidationException::class);

        Server::actions()->changePasswordAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
            'password' => 'NewPleskPass1',
        ]);
    }

    public function test_email_template_is_registered(): void
    {
        $this->assertTrue(EmailTemplate::definitionExists('server.plesk.created'));
    }

    public function test_client_panel_shows_subscription_details(): void
    {
        $this->fakePlesk();

        $this->actingAs($this->customer);

        Volt::test('client_area.default.orders.livewire.plesk-subscription-panel', ['order_id' => $this->provisionedOrder()->id])
            ->assertSee('example.com')
            ->assertSee('203.0.113.30')
            ->assertSee('janedoe1')
            ->assertSee('Login to Plesk');
    }

    public function test_admin_sidebar_shows_subscription_details(): void
    {
        $this->fakePlesk();

        Volt::test('admin_area.default.orders.livewire.plesk-subscription-sidebar', ['order_id' => $this->provisionedOrder()->id])
            ->assertSee('Plesk')
            ->assertSee('example.com')
            ->assertSee('15');
    }

    /**
     * @return array<string, mixed>
     */
    protected function credentials(): array
    {
        return [
            'hostname' => 'https://plesk.example.com',
            'port' => 8443,
            'api_key' => 'APIKEY',
            'username' => 'admin',
            'password' => 'secret',
            'verify_ssl' => '0',
            'debug_mode' => '0',
        ];
    }

    protected function createOrder(): Order
    {
        $order = Order::withoutEvents(fn () => Order::query()->create([
            'user_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'status' => 'pending',
            'cycle_price' => 1,
            'period_in_days' => 30,
        ]));

        $order->prices()->create(['key' => 'domain', 'value' => 'example.com', 'cycle_price' => 0, 'description' => 'Domain', 'type' => 'checkout']);

        return $order->fresh();
    }

    protected function provisionedOrder(): Order
    {
        $order = $this->createOrder();

        $order->update([
            'status' => 'active',
            'external_id' => '15',
            'data' => ['domain_id' => '15', 'domain' => 'example.com', 'plan' => 'Default Domain', 'client_id' => '9', 'client_login' => 'janedoe1', 'ftp_login' => 'exampleabcd'],
        ]);

        $order->createExternalUser([
            'external_id' => '9',
            'username' => 'janedoe1',
            'password' => 'initial-password',
        ]);

        return $order->fresh();
    }

    protected function fakePlesk(): void
    {
        Http::fake(fn (Request $request) => $this->responseFor($request));
    }

    protected function responseFor(Request $request): mixed
    {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $method = $request->method();

        return match (true) {
            $path === '/api/v2/server' => Http::response(['platform' => 'Plesk Obsidian 18.0.70', 'hostname' => 'plesk.example.com']),
            $path === '/api/v2/cli/service_plan/call' => Http::response(['code' => 0, 'stdout' => "Default Domain\nUnlimited\n", 'stderr' => '']),
            $path === '/api/v2/cli/admin/call' => Http::response(['code' => 0, 'stdout' => "https://plesk.example.com:8443/login?secret=abc\n", 'stderr' => '']),
            str_starts_with($path, '/api/v2/cli/') => Http::response(['code' => 0, 'stdout' => 'SUCCESS', 'stderr' => '']),
            $path === '/api/v2/clients' && $method === 'GET' => Http::response($this->customerExists
                ? [['id' => 9, 'login' => 'janedoe1', 'external_id' => 'wemx-user-'.$this->customer->id]]
                : [['id' => 2, 'login' => 'someone', 'external_id' => 'other']]),
            $path === '/api/v2/clients' && $method === 'POST' => Http::response(['id' => 9, 'guid' => 'abc']),
            $path === '/api/v2/domains' && $method === 'POST' => Http::response(['id' => 15, 'guid' => 'def']),
            $path === '/api/v2/domains/15' && $method === 'GET' => Http::response(['id' => 15, 'name' => 'example.com', 'status' => 0, 'ipv4' => ['203.0.113.30']]),
            default => Http::response(['status' => 'success']),
        };
    }
}
