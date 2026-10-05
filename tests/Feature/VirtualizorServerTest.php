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
use Extensions\Servers\Virtualizor\Providers\VirtualizorServiceProvider;
use Extensions\Servers\Virtualizor\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class VirtualizorServerTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $stranger;

    protected ServerConnection $connection;

    protected Package $package;

    protected bool $panelUserExists = false;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Cache::flush();
        Http::preventStrayRequests();

        $this->app->register(VirtualizorServiceProvider::class);
        $this->app['view']->addNamespace('server-virtualizor', base_path('extensions/Servers/Virtualizor/Views'));
        $this->app['translator']->addNamespace('server-virtualizor', base_path('extensions/Servers/Virtualizor/Lang'));
        Volt::mount(base_path('extensions/Servers/Virtualizor/Views'));

        if (! Route::has('virtualizor.login')) {
            require base_path('extensions/Servers/Virtualizor/routes.php');
            $this->app['router']->getRoutes()->refreshNameLookups();
        }

        $this->customer = User::factory()->create(['status' => 'active', 'email' => 'customer@example.com']);
        $this->stranger = User::factory()->create(['status' => 'active']);

        Extension::query()->updateOrCreate(
            ['identifier' => 'server-virtualizor'],
            ['namespace' => Server::class, 'type' => 'server', 'name' => 'Virtualizor', 'status' => 'enabled', 'version' => '1.0.0']
        );

        $this->connection = ServerConnection::query()->create([
            'alias' => 'virt-lab',
            'extension_identifier' => 'server-virtualizor',
            'status' => 'healthy',
            'is_active' => true,
            'prevent_purchasing' => false,
            'receive_alerts' => false,
            'config' => $this->credentials(),
        ]);

        $category = Category::query()->create([
            'name' => 'VPS',
            'slug' => 'vps-'.uniqid(),
            'icon' => 'server',
            'status' => 'active',
        ]);

        $this->package = Package::query()->create([
            'category_id' => $category->id,
            'connection_id' => $this->connection->id,
            'slug' => 'vps-small-'.uniqid(),
            'name' => 'VPS Small '.uniqid(),
            'status' => 'active',
            'data' => [
                'plan_id' => '3',
                'os' => '100',
                'allow_login' => '1',
                'allow_power' => '1',
                'allow_password_change' => '1',
            ],
        ]);
    }

    public function test_connection_test_counts_plans(): void
    {
        $this->fakeVirtualizor();

        $this->assertSame('Connected to Virtualizor. 2 plans found.', Server::testConnection($this->credentials()));

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://virt.example.com:4085/index.php?')
            && str_contains($request->url(), 'act=plans')
            && str_contains($request->url(), 'adminapikey=KEY'));
    }

    public function test_api_errors_are_surfaced(): void
    {
        Http::fake(fn () => Http::response(['error' => ['Invalid API credentials']], 200));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid API credentials');

        Server::testConnection($this->credentials());
    }

    public function test_package_config_lists_plans_and_templates(): void
    {
        $this->fakeVirtualizor();

        $fields = collect((new Server)->setPackageConfig($this->package, $this->connection))->keyBy('key');

        $this->assertSame(['3' => 'Small (KVM)', '4' => 'Large (KVM)'], $fields['plan_id']['options']);
        $this->assertSame(['100' => 'Ubuntu 24.04', '101' => 'Debian 12'], $fields['os']['options']);
        $this->assertTrue($fields->has('allow_power'));
    }

    public function test_package_config_falls_back_to_text_when_offline(): void
    {
        Http::fake(fn () => Http::response('', 500));

        $fields = collect((new Server)->setPackageConfig($this->package, $this->connection))->keyBy('key');

        $this->assertSame('text', $fields['plan_id']['type']);
        $this->assertSame('text', $fields['os']['type']);
    }

    public function test_checkout_asks_for_hostname_and_os(): void
    {
        $this->fakeVirtualizor();

        $fields = collect((new Server)->setCheckoutConfig($this->package))->keyBy('key');

        $this->assertTrue($fields->has('hostname'));
        $this->assertSame('100', $fields['os']['default_value']);
    }

    public function test_create_makes_a_panel_user_and_server(): void
    {
        $this->fakeVirtualizor();

        $order = $this->createOrder();

        (new Server)->create($order, $this->connection);

        $order->refresh();

        $this->assertSame('501', $order->external_id);
        $this->assertSame('203.0.113.20', $order->data['ip']);
        $this->assertSame('Small', $order->data['plan']);
        $this->assertArrayNotHasKey('root_password', $order->data);
        $this->assertArrayNotHasKey('panel_password', $order->data);
        $this->assertSame('customer@example.com', $order->getExternalUser()->username);
        $this->assertNotSame('unknown', $order->getExternalUser()->password);

        $this->assertDatabaseHas('emails', [
            'user_id' => $this->customer->id,
            'identifier' => 'server.virtualizor.created',
        ]);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'act=adduser') && $request['newemail'] === 'customer@example.com');
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'act=addvs')
                && $request['uid'] == 77
                && $request['plid'] == 3
                && $request['virt'] === 'kvm'
                && $request['osid'] == 100
                && $request['hostname'] === 'server1.example.com'
                && $request['ram'] == 2048
                && $request['space'] == 40
                && strlen($request['rootpass']) === 16;
        });
    }

    public function test_create_reuses_an_existing_panel_user(): void
    {
        $this->panelUserExists = true;
        $this->fakeVirtualizor();

        $order = $this->createOrder();

        (new Server)->create($order, $this->connection);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'act=adduser'));
        $this->assertSame('unknown', $order->fresh()->getExternalUser()->password);
    }

    public function test_create_records_virtualizor_errors(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'act=addvs')) {
                return Http::response(['error' => ['No space on any server']], 200);
            }

            return Http::response($this->responseFor($request), 200);
        });

        $order = $this->createOrder();

        try {
            (new Server)->create($order, $this->connection);
            $this->fail('Expected create to fail.');
        } catch (\Exception $exception) {
            $this->assertSame('No space on any server', $exception->getMessage());
        }

        $this->assertSame('No space on any server', $order->fresh()->data['last_error']);
        $this->assertNull($order->fresh()->external_id);
    }

    public function test_create_is_idempotent(): void
    {
        $this->fakeVirtualizor();

        (new Server)->create($this->provisionedOrder(), $this->connection);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'act=addvs'));
    }

    public function test_suspend_unsuspend_and_terminate(): void
    {
        $this->fakeVirtualizor();

        $order = $this->provisionedOrder();
        $server = new Server;

        $server->suspend($order, $this->connection);
        $server->unsuspend($order, $this->connection);
        $server->terminate($order, $this->connection);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'act=vs') && str_contains($request->url(), 'suspend=501') && ! str_contains($request->url(), 'unsuspend'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'unsuspend=501'));
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_contains($request->url(), 'act=vs') && ($request->data()['delete'] ?? null) == 501);
    }

    public function test_upgrade_switches_the_plan(): void
    {
        $this->fakeVirtualizor();

        $newPackage = $this->package->replicate();
        $newPackage->slug = 'vps-large-'.uniqid();
        $newPackage->name = 'VPS Large '.uniqid();
        $newPackage->data = array_merge($this->package->data ?? [], ['plan_id' => '4']);
        $newPackage->save();

        $order = $this->provisionedOrder();

        (new Server)->upgradeOrDowngrade(
            $order,
            PackagePrice::query()->create(['package_id' => $this->package->id, 'period_in_days' => 30, 'price' => 10]),
            PackagePrice::query()->create(['package_id' => $newPackage->id, 'period_in_days' => 30, 'price' => 20]),
            $this->connection,
        );

        $this->assertSame('Large', $order->fresh()->data['plan']);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'act=managevps') && ($request->data()['plid'] ?? null) == 4 && ($request->data()['vpsid'] ?? null) == 501);
    }

    public function test_clients_get_a_panel_login_url(): void
    {
        $this->fakeVirtualizor();

        $url = Server::actions()->loginAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
        ]);

        $this->assertSame('https://virt.example.com:4083/tok123/?as=sid456&svs=501', $url);
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://virt.example.com:4083/index.php?') && str_contains($request->url(), 'act=sso'));
    }

    public function test_login_route_redirects(): void
    {
        $this->fakeVirtualizor();

        $order = $this->provisionedOrder();

        $this->withoutMiddleware([InstallAppMiddleware::class, SyncRuntimeMiddleware::class])
            ->actingAs($this->customer)
            ->get(route('virtualizor.login', $order))
            ->assertRedirect('https://virt.example.com:4083/tok123/?as=sid456&svs=501');
    }

    public function test_strangers_cannot_control_the_server(): void
    {
        $this->fakeVirtualizor();

        $this->expectException(ValidationException::class);

        Server::actions()->powerAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->stranger->id,
            'action' => 'stop',
        ]);
    }

    public function test_power_actions_are_sent(): void
    {
        $this->fakeVirtualizor();

        Server::actions()->powerAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
            'action' => 'restart',
        ]);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'act=vs') && str_contains($request->url(), 'action=restart') && str_contains($request->url(), 'vpsid=501'));
    }

    public function test_unknown_power_actions_are_rejected(): void
    {
        $this->fakeVirtualizor();

        $this->expectException(ValidationException::class);

        Server::actions()->powerAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
            'action' => 'rebuild',
        ]);
    }

    public function test_power_is_blocked_when_the_package_disables_it(): void
    {
        $this->fakeVirtualizor();

        $this->package->update(['data' => array_merge($this->package->data ?? [], ['allow_power' => '0'])]);

        $this->expectException(ValidationException::class);

        Server::actions()->powerAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
            'action' => 'start',
        ]);
    }

    public function test_clients_can_change_the_root_password(): void
    {
        $this->fakeVirtualizor();

        Server::actions()->changeRootPasswordAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
            'password' => 'NewRootPass123',
        ]);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'act=managevps') && ($request->data()['rootpass'] ?? null) === 'NewRootPass123');
    }

    public function test_email_template_is_registered(): void
    {
        $this->assertTrue(EmailTemplate::definitionExists('server.virtualizor.created'));
    }

    public function test_client_panel_shows_server_details(): void
    {
        $this->fakeVirtualizor();

        $order = $this->provisionedOrder();

        $this->actingAs($this->customer);

        Volt::test('client_area.default.orders.livewire.virtualizor-server-panel', ['order_id' => $order->id])
            ->assertSee('server1.example.com')
            ->assertSee('203.0.113.20')
            ->assertSee('Ubuntu 24.04')
            ->assertSee('Open control panel')
            ->call('power', 'start')
            ->assertHasNoErrors();
    }

    public function test_admin_sidebar_shows_server_details(): void
    {
        $this->fakeVirtualizor();

        Volt::test('admin_area.default.orders.livewire.virtualizor-server-sidebar', ['order_id' => $this->provisionedOrder()->id])
            ->assertSee('Virtualizor')
            ->assertSee('501')
            ->assertSee('203.0.113.20');
    }

    /**
     * @return array<string, mixed>
     */
    protected function credentials(): array
    {
        return [
            'hostname' => 'https://virt.example.com',
            'port' => 4085,
            'client_port' => 4083,
            'api_key' => 'KEY',
            'api_password' => 'PASS',
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

        $order->prices()->createMany([
            ['key' => 'hostname', 'value' => 'server1.example.com', 'cycle_price' => 0, 'description' => 'Hostname', 'type' => 'checkout'],
        ]);

        return $order->fresh();
    }

    protected function provisionedOrder(): Order
    {
        $order = $this->createOrder();

        $order->update([
            'status' => 'active',
            'external_id' => '501',
            'data' => ['vpsid' => '501', 'hostname' => 'server1.example.com', 'ip' => '203.0.113.20', 'plan' => 'Small', 'plan_id' => '3'],
        ]);

        $order->createExternalUser([
            'external_id' => '77',
            'username' => 'customer@example.com',
            'password' => 'panel-password',
        ]);

        return $order->fresh();
    }

    protected function fakeVirtualizor(): void
    {
        Http::fake(fn (Request $request) => Http::response($this->responseFor($request), 200));
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseFor(Request $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return match ($query['act'] ?? '') {
            'plans' => ['plans' => [
                '3' => ['plid' => '3', 'plan_name' => 'Small', 'virt' => 'kvm', 'ram' => 2048, 'space' => 40, 'cores' => 2, 'ips' => 1],
                '4' => ['plid' => '4', 'plan_name' => 'Large', 'virt' => 'kvm', 'ram' => 8192, 'space' => 160, 'cores' => 4, 'ips' => 1],
            ]],
            'os' => ['oslist' => ['kvm' => ['ubuntu' => ['100' => ['name' => 'Ubuntu 24.04']], 'debian' => ['101' => ['name' => 'Debian 12']]]]],
            'users' => ['users' => ($this->panelUserExists || $this->hasSentAddUser())
                ? ['77' => ['uid' => '77', 'email' => 'customer@example.com']]
                : []],
            'adduser' => ['done' => 1],
            'addvs' => ['done' => 1, 'newvs' => ['vpsid' => 501, 'ips' => ['203.0.113.20']]],
            'vs' => ['done' => 1, 'vs' => ['501' => [
                'vpsid' => '501', 'hostname' => 'server1.example.com', 'ips' => ['203.0.113.20'],
                'os_name' => 'Ubuntu 24.04', 'ram' => 2048, 'space' => 40, 'cores' => 2, 'bandwidth' => 1000, 'suspended' => '0',
            ]]],
            'sso' => ['sid' => 'sid456', 'token_key' => 'tok123'],
            default => ['done' => 1],
        };
    }

    protected function hasSentAddUser(): bool
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), 'act=adduser'))->isNotEmpty();
    }
}
