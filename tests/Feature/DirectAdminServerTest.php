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
use Extensions\Servers\DirectAdmin\Providers\DirectAdminServiceProvider;
use Extensions\Servers\DirectAdmin\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DirectAdminServerTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $stranger;

    protected ServerConnection $connection;

    protected Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Cache::flush();
        Http::preventStrayRequests();

        $this->app->register(DirectAdminServiceProvider::class);
        $this->app['view']->addNamespace('server-directadmin', base_path('extensions/Servers/DirectAdmin/Views'));
        $this->app['translator']->addNamespace('server-directadmin', base_path('extensions/Servers/DirectAdmin/Lang'));
        Volt::mount(base_path('extensions/Servers/DirectAdmin/Views'));

        if (! Route::has('directadmin.login')) {
            require base_path('extensions/Servers/DirectAdmin/routes.php');
            $this->app['router']->getRoutes()->refreshNameLookups();
        }

        $this->customer = User::factory()->create(['status' => 'active']);
        $this->stranger = User::factory()->create(['status' => 'active']);

        Extension::query()->updateOrCreate(
            ['identifier' => 'server-directadmin'],
            [
                'namespace' => Server::class,
                'type' => 'server',
                'name' => 'DirectAdmin',
                'status' => 'enabled',
                'version' => '1.0.0',
            ]
        );

        $this->connection = ServerConnection::query()->create([
            'alias' => 'da-lab',
            'extension_identifier' => 'server-directadmin',
            'status' => 'healthy',
            'is_active' => true,
            'prevent_purchasing' => false,
            'receive_alerts' => false,
            'config' => $this->credentials(),
        ]);

        $category = Category::query()->create([
            'name' => 'Shared hosting',
            'slug' => 'shared-hosting-'.uniqid(),
            'icon' => 'server',
            'status' => 'active',
        ]);

        $this->package = Package::query()->create([
            'category_id' => $category->id,
            'connection_id' => $this->connection->id,
            'slug' => 'da-starter-'.uniqid(),
            'name' => 'DA Starter '.uniqid(),
            'status' => 'active',
            'data' => [
                'package' => 'starter',
                'ip' => '',
                'notify' => '0',
                'domain' => 'example.com',
                'allow_login' => '1',
                'allow_password_change' => '1',
            ],
        ]);
    }

    public function test_connection_config_includes_hostname_and_login_key(): void
    {
        $keys = collect((new Server)->setConfig())->pluck('key');

        $this->assertTrue($keys->contains('hostname'));
        $this->assertTrue($keys->contains('port'));
        $this->assertTrue($keys->contains('username'));
        $this->assertTrue($keys->contains('password'));
    }

    public function test_package_config_lists_directadmin_packages_and_ips(): void
    {
        $this->fakeDirectAdmin();

        $fields = collect((new Server)->setPackageConfig($this->package, $this->connection))->keyBy('key');

        $this->assertSame(['starter' => 'starter', 'pro' => 'pro'], $fields['package']['options']);
        $this->assertSame(['203.0.113.10' => '203.0.113.10'], $fields['ip']['options']);
        $this->assertTrue($fields->has('allow_login'));
        $this->assertTrue($fields->has('allow_password_change'));
    }

    public function test_package_config_falls_back_to_text_inputs_when_offline(): void
    {
        Http::fake(fn () => Http::response('', 500));

        $fields = collect((new Server)->setPackageConfig($this->package, $this->connection))->keyBy('key');

        $this->assertSame('text', $fields['package']['type']);
        $this->assertSame('text', $fields['ip']['type']);
    }

    public function test_checkout_config_asks_for_a_domain(): void
    {
        $keys = collect((new Server)->setCheckoutConfig($this->package))->pluck('key');

        $this->assertTrue($keys->contains('domain'));
        $this->assertTrue($keys->contains('username'));
    }

    public function test_test_connection_lists_users(): void
    {
        $this->fakeDirectAdmin();

        $this->assertSame('Connected to DirectAdmin. 2 accounts found.', Server::testConnection($this->credentials()));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://da.example.com:2222/CMD_API_SHOW_USERS'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('admin:LOGINKEY')));
    }

    public function test_test_connection_fails_on_an_html_login_page(): void
    {
        Http::fake(fn () => Http::response('<html><body>Login</body></html>', 200));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('HTML page');

        Server::testConnection($this->credentials());
    }

    public function test_test_connection_fails_on_rejected_credentials(): void
    {
        Http::fake(fn () => Http::response('', 401));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('rejected the credentials');

        Server::testConnection($this->credentials());
    }

    public function test_create_provisions_the_account_and_stores_credentials(): void
    {
        $this->fakeDirectAdmin();

        $order = $this->createOrder();

        (new Server)->create($order, $this->connection);

        $order->refresh();

        $this->assertSame('example', $order->external_id);
        $this->assertSame('example.com', $order->data['domain']);
        $this->assertSame('203.0.113.10', $order->data['ip']);
        $this->assertSame('starter', $order->data['package']);
        $this->assertArrayNotHasKey('password', $order->data);
        $this->assertSame('example', $order->getExternalUser()->username);
        $this->assertNotSame('unknown', $order->getExternalUser()->password);

        $this->assertDatabaseHas('emails', [
            'user_id' => $this->customer->id,
            'identifier' => 'server.directadmin.created',
            'subject' => 'Your hosting account is ready',
        ]);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/CMD_API_ACCOUNT_USER')
                && $request['action'] === 'create'
                && $request['username'] === 'example'
                && $request['domain'] === 'example.com'
                && $request['package'] === 'starter'
                && $request['ip'] === '203.0.113.10'
                && $request['notify'] === 'no'
                && $request['email'] === $this->customer->email;
        });
    }

    public function test_create_records_the_directadmin_error(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/CMD_API_ACCOUNT_USER')) {
                return Http::response('error=1&text=Unable to create user&details=That domain already exists', 200);
            }

            return Http::response('list[]=203.0.113.10', 200);
        });

        $order = $this->createOrder();

        try {
            (new Server)->create($order, $this->connection);
            $this->fail('Expected the create call to fail.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('That domain already exists', $exception->getMessage());
        }

        $this->assertStringContainsString('Unable to create user', $order->fresh()->data['last_error']);
        $this->assertNull($order->fresh()->external_id);
    }

    public function test_create_is_idempotent_when_the_remote_id_already_exists(): void
    {
        $this->fakeDirectAdmin();

        $order = $this->provisionedOrder();

        (new Server)->create($order, $this->connection);

        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/CMD_API_ACCOUNT_USER'));
        $this->assertSame('example', $order->fresh()->external_id);
    }

    public function test_suspend_unsuspend_and_terminate_call_directadmin(): void
    {
        $this->fakeDirectAdmin();

        $order = $this->provisionedOrder();
        $server = new Server;

        $server->suspend($order, $this->connection);
        $server->unsuspend($order, $this->connection);
        $server->terminate($order, $this->connection);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/CMD_API_SELECT_USERS') && ($request->data()['dosuspend'] ?? null) == 1 && $request['select0'] === 'example');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/CMD_API_SELECT_USERS') && ($request->data()['dounsuspend'] ?? null) == 1 && $request['select0'] === 'example');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/CMD_API_SELECT_USERS') && ($request->data()['delete'] ?? null) === 'yes' && $request['select0'] === 'example');
    }

    public function test_upgrade_changes_the_package(): void
    {
        $this->fakeDirectAdmin();

        $newPackage = $this->package->replicate();
        $newPackage->slug = 'da-pro-'.uniqid();
        $newPackage->name = 'DA Pro '.uniqid();
        $newPackage->data = array_merge($this->package->data ?? [], ['package' => 'pro']);
        $newPackage->save();

        $order = $this->provisionedOrder();

        (new Server)->upgradeOrDowngrade(
            $order,
            PackagePrice::query()->create(['package_id' => $this->package->id, 'period_in_days' => 30, 'price' => 10]),
            PackagePrice::query()->create(['package_id' => $newPackage->id, 'period_in_days' => 30, 'price' => 20]),
            $this->connection,
        );

        $this->assertSame('pro', $order->fresh()->data['package']);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/CMD_API_MODIFY_USER')
            && $request['action'] === 'package'
            && $request['user'] === 'example'
            && $request['package'] === 'pro');
    }

    public function test_clients_get_a_one_time_login_url(): void
    {
        $this->fakeDirectAdmin();

        $url = Server::actions()->loginAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
        ]);

        $this->assertSame('https://da.example.com:2222/api/login/url?key=abc', $url);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/CMD_API_LOGIN_KEYS')
            && $request['type'] === 'one_time_url'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('admin|example:LOGINKEY')));
    }

    public function test_client_login_route_redirects_to_directadmin(): void
    {
        $this->fakeDirectAdmin();

        $order = $this->provisionedOrder();

        $this->withoutMiddleware([InstallAppMiddleware::class, SyncRuntimeMiddleware::class])
            ->actingAs($this->customer)
            ->get(route('directadmin.login', $order))
            ->assertRedirect('https://da.example.com:2222/api/login/url?key=abc');
    }

    public function test_clients_cannot_open_someone_elses_account(): void
    {
        $this->fakeDirectAdmin();

        $this->expectException(ValidationException::class);

        Server::actions()->loginAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->stranger->id,
        ]);
    }

    public function test_login_is_blocked_when_the_package_disables_it(): void
    {
        $this->fakeDirectAdmin();

        $this->package->update([
            'data' => array_merge($this->package->data ?? [], ['allow_login' => '0']),
        ]);

        $this->expectException(ValidationException::class);

        Server::actions()->loginAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
        ]);
    }

    public function test_login_is_blocked_while_suspended(): void
    {
        $this->fakeDirectAdmin();

        $order = $this->provisionedOrder();
        $order->update(['status' => 'suspended']);

        $this->expectException(ValidationException::class);

        Server::actions()->loginAsClient([
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
        ]);
    }

    public function test_clients_can_change_the_password(): void
    {
        $this->fakeDirectAdmin();

        $order = $this->provisionedOrder();

        Server::actions()->changePasswordAsClient([
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'password' => 'NewSecret123',
        ]);

        $this->assertSame('NewSecret123', $order->getExternalUser()->fresh()->password);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/CMD_API_USER_PASSWD')
            && $request['username'] === 'example'
            && $request['passwd'] === 'NewSecret123'
            && $request['passwd2'] === 'NewSecret123');
    }

    public function test_password_change_is_blocked_when_the_package_disables_it(): void
    {
        $this->fakeDirectAdmin();

        $this->package->update([
            'data' => array_merge($this->package->data ?? [], ['allow_password_change' => '0']),
        ]);

        $this->expectException(ValidationException::class);

        Server::actions()->changePasswordAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
            'password' => 'NewSecret123',
        ]);
    }

    public function test_uses_directadmin_only_for_directadmin_orders(): void
    {
        $this->assertTrue(Server::usesDirectAdmin($this->provisionedOrder()));
        $this->assertFalse(Server::usesDirectAdmin(null));
    }

    public function test_email_template_is_registered(): void
    {
        $this->assertTrue(EmailTemplate::definitionExists('server.directadmin.created'));
    }

    public function test_client_panel_shows_account_details_and_usage(): void
    {
        $this->fakeDirectAdmin();

        $order = $this->provisionedOrder();

        $this->actingAs($this->customer);

        Volt::test('client_area.default.orders.livewire.directadmin-account-panel', ['order_id' => $order->id])
            ->assertSee('DirectAdmin account')
            ->assertSee('example.com')
            ->assertSee('203.0.113.10')
            ->assertSee('ns1.example.com')
            ->assertSee('120.5')
            ->assertSee('Login to DirectAdmin');
    }

    public function test_client_panel_can_change_the_password(): void
    {
        $this->fakeDirectAdmin();

        $order = $this->provisionedOrder();

        $this->actingAs($this->customer);

        Volt::test('client_area.default.orders.livewire.directadmin-account-panel', ['order_id' => $order->id])
            ->set('password', 'PanelSecret123')
            ->call('changePassword')
            ->assertHasNoErrors();

        $this->assertSame('PanelSecret123', $order->getExternalUser()->fresh()->password);
    }

    public function test_admin_sidebar_shows_account_details(): void
    {
        $this->fakeDirectAdmin();

        $order = $this->provisionedOrder();

        Volt::test('admin_area.default.orders.livewire.directadmin-account-sidebar', ['order_id' => $order->id])
            ->assertSee('DirectAdmin')
            ->assertSee('example.com')
            ->assertSee('starter');
    }

    /**
     * @return array<string, mixed>
     */
    protected function credentials(): array
    {
        return [
            'hostname' => 'https://da.example.com',
            'port' => 2222,
            'username' => 'admin',
            'password' => 'LOGINKEY',
            'verify_ssl' => '0',
            'debug_mode' => '0',
        ];
    }

    protected function createOrder(): Order
    {
        return Order::withoutEvents(fn () => Order::query()->create([
            'user_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'status' => 'pending',
            'cycle_price' => 1,
            'period_in_days' => 30,
        ]));
    }

    protected function provisionedOrder(): Order
    {
        $order = $this->createOrder();

        $order->update([
            'status' => 'active',
            'external_id' => 'example',
            'data' => [
                'username' => 'example',
                'domain' => 'example.com',
                'ip' => '203.0.113.10',
                'package' => 'starter',
            ],
        ]);

        $order->createExternalUser([
            'external_id' => 'example',
            'username' => 'example',
            'password' => 'initial-password',
            'data' => $order->data,
        ]);

        return $order->fresh();
    }

    protected function fakeDirectAdmin(): void
    {
        Http::fake(function (Request $request) {
            $command = basename(parse_url($request->url(), PHP_URL_PATH) ?: '');

            return Http::response(match ($command) {
                'CMD_API_SHOW_USERS' => 'list[]=alice&list[]=bob',
                'CMD_API_PACKAGES_USER' => 'list[]=starter&list[]=pro',
                'CMD_API_SHOW_RESELLER_IPS' => 'list[]=203.0.113.10',
                'CMD_API_SHOW_USER_CONFIG' => 'domain=example.com&ip=203.0.113.10&package=starter&suspended=no&quota=1000&bandwidth=unlimited&ns1=ns1.example.com&ns2=ns2.example.com',
                'CMD_API_SHOW_USER_USAGE' => 'quota=120.5&bandwidth=42',
                'CMD_API_LOGIN_KEYS' => 'error=0&text=Login URL created&details='.urlencode('https://da.example.com:2222/api/login/url?key=abc'),
                default => 'error=0&text=Success&details=',
            }, 200);
        });
    }
}
