<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Extension;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\ServerConnection;
use App\Models\User;
use Extensions\Servers\Cpanel\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CpanelServerTest extends TestCase
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
        Http::preventStrayRequests();

        $this->app['view']->addNamespace('server-cpanel', base_path('extensions/Servers/Cpanel/Views'));
        $this->app['translator']->addNamespace('server-cpanel', base_path('extensions/Servers/Cpanel/Lang'));
        Volt::mount(base_path('extensions/Servers/Cpanel/Views'));

        if (! Route::has('cpanel.login')) {
            require base_path('extensions/Servers/Cpanel/routes.php');
            $this->app['router']->getRoutes()->refreshNameLookups();
        }

        $this->customer = User::factory()->create(['status' => 'active']);
        $this->stranger = User::factory()->create(['status' => 'active']);

        Extension::query()->updateOrCreate(
            ['identifier' => 'server-cpanel'],
            [
                'namespace' => Server::class,
                'type' => 'server',
                'name' => 'cPanel & WHM',
                'status' => 'enabled',
                'version' => '1.0.0',
            ]
        );

        $this->connection = ServerConnection::query()->create([
            'alias' => 'whm-lab',
            'extension_identifier' => 'server-cpanel',
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
            'slug' => 'cpanel-starter-'.uniqid(),
            'name' => 'cPanel Starter '.uniqid(),
            'status' => 'active',
            'data' => [
                'package' => 'default',
                'owner' => 'root',
                'quota' => 1024,
                'bandwidth' => 10240,
                'inode' => 200000,
                'max_ftp' => 5,
                'max_email' => 10,
                'max_addon' => 2,
                'max_subdomains' => 5,
                'max_sql' => 5,
                'dedicated_ip' => 'n',
                'cgi' => '1',
                'shell' => 'n',
                'theme' => 'jupiter',
                'locale' => 'en',
                'domain' => 'example.com',
                'allow_login' => '1',
                'allow_password_change' => '1',
                'allow_email' => '1',
                'allow_ftp' => '1',
                'allow_databases' => '1',
                'allow_domains' => '1',
                'allow_ssl' => '1',
                'allow_backups' => '1',
            ],
        ]);
    }

    public function test_connection_config_includes_token_and_hostname_settings(): void
    {
        $keys = collect((new Server)->setConfig())->pluck('key');

        $this->assertTrue($keys->contains('hostname'));
        $this->assertTrue($keys->contains('api_token'));
        $this->assertTrue($keys->contains('access_hash'));
        $this->assertTrue($keys->contains('debug_mode'));
    }

    public function test_package_config_exposes_plan_limits_and_feature_flags(): void
    {
        $this->fakeWhm();

        $keys = collect((new Server)->setPackageConfig($this->package, $this->connection))->pluck('key');

        $this->assertTrue($keys->contains('package'));
        $this->assertTrue($keys->contains('quota'));
        $this->assertTrue($keys->contains('bandwidth'));
        $this->assertTrue($keys->contains('allow_login'));
        $this->assertTrue($keys->contains('allow_email'));
        $this->assertTrue($keys->contains('allow_ssl'));
    }

    public function test_checkout_config_asks_for_a_domain(): void
    {
        $keys = collect((new Server)->setCheckoutConfig($this->package))->pluck('key');

        $this->assertTrue($keys->contains('domain'));
        $this->assertTrue($keys->contains('username'));
    }

    public function test_test_connection_reads_the_whm_version(): void
    {
        $this->fakeWhm();

        $message = Server::testConnection($this->credentials());

        $this->assertSame('Connected to cPanel & WHM 11.124.0.15.', $message);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/json-api/version'));
    }

    public function test_test_connection_fails_when_whm_rejects_the_token(): void
    {
        Http::fake([
            'https://whm.example.com:2087/json-api/version' => Http::response([
                'metadata' => [
                    'result' => 0,
                    'reason' => 'Invalid API token',
                ],
            ], 200),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid API token');

        Server::testConnection($this->credentials());
    }

    public function test_create_provisions_the_account_and_stores_credentials(): void
    {
        $this->fakeWhm();

        $order = $this->createOrder();

        (new Server)->create($order, $this->connection);

        $order->refresh();

        $this->assertSame('example', $order->external_id);
        $this->assertSame('example.com', $order->data['domain']);
        $this->assertSame('203.0.113.10', $order->data['ip']);
        $this->assertSame('default', $order->data['package']);
        $this->assertContains('ns1.example.com', $order->data['nameservers']);
        $this->assertTrue($order->hasExternalUser());
        $this->assertSame('example', $order->getExternalUser()->username);
        $this->assertNotSame('unknown', $order->getExternalUser()->password);

        $this->assertDatabaseHas('emails', [
            'user_id' => $this->customer->id,
            'identifier' => 'server.cpanel.created',
        ]);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_contains($request->url(), '/json-api/createacct'));
    }

    public function test_create_is_idempotent_when_the_remote_id_already_exists(): void
    {
        $this->fakeWhm();

        $order = $this->provisionedOrder();

        (new Server)->create($order, $this->connection);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/json-api/createacct'));
        $this->assertSame('example', $order->fresh()->external_id);
    }

    public function test_suspend_unsuspend_and_terminate_call_whm(): void
    {
        $this->fakeWhm();

        $order = $this->provisionedOrder();
        $server = new Server;

        $server->suspend($order, $this->connection);
        $server->unsuspend($order, $this->connection);
        $server->terminate($order, $this->connection);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/json-api/suspendacct'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/json-api/unsuspendacct'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/json-api/removeacct'));
    }

    public function test_upgrade_changes_the_package_and_limits(): void
    {
        $this->fakeWhm();

        $newPackage = $this->package->replicate();
        $newPackage->slug = 'cpanel-plus-'.uniqid();
        $newPackage->name = 'cPanel Plus '.uniqid();
        $newPackage->data = array_merge($this->package->data ?? [], [
            'package' => 'plus',
            'quota' => 2048,
            'bandwidth' => 20480,
        ]);
        $newPackage->save();

        $newPrice = PackagePrice::query()->create([
            'package_id' => $newPackage->id,
            'period_in_days' => 30,
            'price' => 20,
        ]);

        $order = $this->provisionedOrder();

        (new Server)->upgradeOrDowngrade(
            $order,
            PackagePrice::query()->create(['package_id' => $this->package->id, 'period_in_days' => 30, 'price' => 10]),
            $newPrice,
            $this->connection,
        );

        $this->assertSame('plus', $order->fresh()->data['package']);
        $this->assertSame(2048, $order->fresh()->data['quota']);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/json-api/changepackage'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/json-api/editquota'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/json-api/limitbw'));
    }

    public function test_clients_can_open_a_cpanel_session(): void
    {
        $this->fakeWhm();

        $session = Server::actions()->loginAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
        ]);

        $this->assertStringContainsString('cpsess', $session['url']);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/json-api/create_user_session'));
    }

    public function test_clients_cannot_control_someone_elses_account(): void
    {
        $this->fakeWhm();

        $this->expectException(ValidationException::class);

        Server::actions()->loginAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->stranger->id,
        ]);
    }

    public function test_clients_can_change_the_cpanel_password(): void
    {
        $this->fakeWhm();

        $order = $this->provisionedOrder();

        Server::actions()->changePasswordAsClient([
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'password' => 'NewSecret123!',
        ]);

        $this->assertSame('NewSecret123!', $order->getExternalUser()->fresh()->password);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/json-api/passwd'));
    }

    public function test_password_change_is_blocked_when_the_package_disables_it(): void
    {
        $this->fakeWhm();
        $this->package->update([
            'data' => array_merge($this->package->data ?? [], ['allow_password_change' => '0']),
        ]);

        $this->expectException(ValidationException::class);

        Server::actions()->changePasswordAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
            'password' => 'NewSecret123!',
        ]);
    }

    public function test_login_is_blocked_when_the_package_disables_it(): void
    {
        $this->fakeWhm();
        $this->package->update([
            'data' => array_merge($this->package->data ?? [], ['allow_login' => '0']),
        ]);

        $this->expectException(ValidationException::class);

        Server::actions()->loginAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
        ]);
    }

    public function test_email_tools_are_blocked_when_the_package_disables_them(): void
    {
        $this->fakeWhm();
        $this->package->update([
            'data' => array_merge($this->package->data ?? [], ['allow_email' => '0']),
        ]);

        $this->expectException(ValidationException::class);

        Server::actions()->createEmailAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
            'email' => 'info',
            'password' => 'Mailbox123!',
        ]);
    }

    public function test_clients_can_create_an_email_account(): void
    {
        $this->fakeWhm();

        Server::actions()->createEmailAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
            'email' => 'info',
            'password' => 'Mailbox123!',
            'quota' => 250,
        ]);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/json-api/cpanel')
                && $request['cpanel_jsonapi_module'] === 'Email'
                && $request['cpanel_jsonapi_func'] === 'add_pop';
        });
    }

    public function test_validation_exception_is_thrown_for_bad_email_input(): void
    {
        $this->fakeWhm();

        $this->expectException(ValidationException::class);

        Server::actions()->createEmailAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
            'email' => 'not a mailbox',
            'password' => 'short',
        ]);
    }

    public function test_uses_cpanel_only_for_cpanel_orders(): void
    {
        $this->assertTrue(Server::usesCpanel($this->provisionedOrder()));
        $this->assertFalse(Server::usesCpanel(null));
    }

    public function test_client_panel_shows_account_details(): void
    {
        $this->fakeWhm();

        $order = $this->provisionedOrder();

        $this->actingAs($this->customer);

        Volt::test('client_area.default.orders.livewire.account-panel', ['order_id' => $order->id])
            ->assertSee('cPanel account')
            ->assertSee('example.com')
            ->assertSee('example')
            ->assertSee('203.0.113.10');
    }

    /**
     * @return array<string, mixed>
     */
    protected function credentials(): array
    {
        return [
            'hostname' => 'https://whm.example.com',
            'port' => 2087,
            'username' => 'root',
            'api_token' => 'WHMTOKEN123',
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
                'package' => 'default',
                'theme' => 'jupiter',
                'nameservers' => ['ns1.example.com', 'ns2.example.com'],
                'quota' => 1024,
                'bandwidth' => 10240,
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

    protected function fakeWhm(): void
    {
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';

            if (str_ends_with($path, '/version')) {
                return Http::response($this->whmOk([
                    'version' => '11.124.0.15',
                ]), 200);
            }

            if (str_ends_with($path, '/listpkgs')) {
                return Http::response($this->whmOk([
                    'pkg' => [
                        ['name' => 'default', 'QUOTA' => 1024],
                        ['name' => 'plus', 'QUOTA' => 2048],
                    ],
                ]), 200);
            }

            if (str_ends_with($path, '/list_styles')) {
                return Http::response($this->whmOk([
                    'style' => [
                        ['name' => 'jupiter'],
                    ],
                ]), 200);
            }

            if (str_ends_with($path, '/accountsummary')) {
                return Http::response($this->whmOk([
                    'acct' => [[
                        'user' => 'example',
                        'domain' => 'example.com',
                        'ip' => '203.0.113.10',
                        'plan' => 'default',
                        'diskused' => '12M',
                        'disklimit' => '1024M',
                        'suspended' => 0,
                        'theme' => 'jupiter',
                    ]],
                ]), 200);
            }

            if (str_ends_with($path, '/createacct')) {
                return Http::response($this->whmOk([
                    'ip' => '203.0.113.10',
                    'nameserver' => 'ns1.example.com',
                    'nameserver2' => 'ns2.example.com',
                    'nameservers' => ['ns1.example.com', 'ns2.example.com'],
                    'package' => 'default',
                ]), 200);
            }

            if (str_ends_with($path, '/create_user_session')) {
                return Http::response($this->whmOk([
                    'url' => 'https://whm.example.com:2083/cpsess1234567890/login/?session=abc',
                    'session' => 'abc',
                    'cp_security_token' => '/cpsess1234567890',
                ]), 200);
            }

            if (str_ends_with($path, '/cpanel')) {
                return Http::response([
                    'result' => [
                        'status' => 1,
                        'data' => $this->uapiData($request),
                    ],
                ], 200);
            }

            if (in_array($request->method(), ['POST', 'GET'], true)) {
                return Http::response($this->whmOk([]), 200);
            }

            return Http::response($this->whmOk([]), 200);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function whmOk(array $data): array
    {
        return [
            'metadata' => [
                'result' => 1,
                'reason' => 'OK',
                'version' => 1,
            ],
            'data' => $data,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function uapiData(Request $request): array
    {
        $function = (string) ($request['cpanel_jsonapi_func'] ?? '');

        return match ($function) {
            'list_pops_with_disk' => [['email' => 'info@example.com']],
            'list_ftp_with_disk' => [['user' => 'example']],
            'list_databases' => [['database' => 'example_wp']],
            'list_users' => [['user' => 'example_wp']],
            'list_domains' => [['main_domain' => 'example.com']],
            'listaddondomains' => [],
            'listsubdomains' => [],
            'listparkeddomains' => [],
            'installed_hosts' => [['servername' => 'example.com']],
            'list_backups' => [],
            'get_usages' => [
                ['id' => 'disk_usage', 'usage' => '12', 'maximum' => '1024'],
                ['id' => 'bandwidth', 'usage' => '100', 'maximum' => '10240'],
            ],
            default => [],
        };
    }
}
