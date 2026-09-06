<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Extension;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\ServerConnection;
use App\Models\User;
use Extensions\Servers\Proxmox\Server;
use Extensions\Servers\Proxmox\Support\IpPool;
use Extensions\Servers\Proxmox\Support\OsTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProxmoxServerTest extends TestCase
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

        $this->app['view']->addNamespace('server-proxmox', base_path('extensions/Servers/Proxmox/Views'));
        $this->app['translator']->addNamespace('server-proxmox', base_path('extensions/Servers/Proxmox/Lang'));
        Volt::mount(base_path('extensions/Servers/Proxmox/Views'));

        if (! Route::has('proxmox.console')) {
            require base_path('extensions/Servers/Proxmox/routes.php');
            $this->app['router']->getRoutes()->refreshNameLookups();
        }

        $this->customer = User::factory()->create(['status' => 'active']);
        $this->stranger = User::factory()->create(['status' => 'active']);

        Extension::query()->updateOrCreate(
            ['identifier' => 'server-proxmox'],
            [
                'namespace' => Server::class,
                'type' => 'server',
                'name' => 'Proxmox VE',
                'status' => 'enabled',
                'version' => '1.0.0',
            ]
        );

        $this->connection = ServerConnection::query()->create([
            'alias' => 'proxmox-lab',
            'extension_identifier' => 'server-proxmox',
            'status' => 'healthy',
            'is_active' => true,
            'prevent_purchasing' => false,
            'receive_alerts' => false,
            'config' => $this->credentials(),
        ]);

        $category = Category::query()->create([
            'name' => 'Virtual servers',
            'slug' => 'virtual-servers-'.uniqid(),
            'icon' => 'server',
            'status' => 'active',
        ]);

        $this->package = Package::query()->create([
            'category_id' => $category->id,
            'connection_id' => $this->connection->id,
            'slug' => 'ubuntu-kvm-'.uniqid(),
            'name' => 'Ubuntu KVM '.uniqid(),
            'status' => 'active',
            'data' => [
                'os_templates' => "ubuntu-24.04|Ubuntu 24.04 LTS|9000\ndebian-12|Debian 12|9002",
                'os_template' => 'ubuntu-24.04',
                'node' => 'pve',
                'storage' => 'local-lvm',
                'bridge' => 'vmbr0',
                'cores' => 2,
                'sockets' => 1,
                'cpu_type' => 'host',
                'memory' => 2048,
                'balloon' => 0,
                'disk' => 40,
                'disk_slot' => 'scsi0',
                'ip_mode' => 'dhcp',
                'ci_user' => 'root',
                'hostname_prefix' => 'vps-',
                'start_after_create' => '1',
                'full_clone' => '1',
                'allow_reinstall' => '1',
                'allow_console' => '1',
                'snapshot_limit' => 3,
                'qemu_agent' => '1',
                'onboot' => '1',
            ],
        ]);
    }

    public function test_os_templates_are_parsed_into_checkout_options(): void
    {
        $options = OsTemplates::options("ubuntu-24.04|Ubuntu 24.04 LTS|9000\ndebian-12|Debian 12|9002");

        $this->assertSame('Ubuntu 24.04 LTS (VM 9000)', $options['ubuntu-24.04']);
        $this->assertSame(9002, OsTemplates::find('debian-12|Debian 12|9002', 'debian-12')['vmid']);
    }

    public function test_ip_pool_returns_the_next_unused_address(): void
    {
        $this->assertSame('192.168.10.12', IpPool::nextAvailable('192.168.10.10-192.168.10.14', [
            '192.168.10.10',
            '192.168.10.11',
        ]));
    }

    public function test_connection_config_includes_token_and_node_settings(): void
    {
        $keys = collect((new Server)->setConfig())->pluck('key');

        $this->assertTrue($keys->contains('hostname'));
        $this->assertTrue($keys->contains('token_secret'));
        $this->assertTrue($keys->contains('default_storage'));
        $this->assertTrue($keys->contains('ip_pool'));
    }

    public function test_package_config_exposes_os_cpu_memory_and_disk_settings(): void
    {
        $this->fakeProxmox();

        $keys = collect((new Server)->setPackageConfig($this->package, $this->connection))->pluck('key');

        $this->assertTrue($keys->contains('os_templates'));
        $this->assertTrue($keys->contains('os_template'));
        $this->assertTrue($keys->contains('cores'));
        $this->assertTrue($keys->contains('memory'));
        $this->assertTrue($keys->contains('disk'));
        $this->assertTrue($keys->contains('ip_mode'));
        $this->assertTrue($keys->contains('snapshot_limit'));
    }

    public function test_test_connection_reads_the_proxmox_version(): void
    {
        $this->fakeProxmox();

        $message = Server::testConnection($this->credentials());

        $this->assertSame('Connected to Proxmox VE 8.3.', $message);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/version'));
    }

    public function test_event_add_to_cart_rejects_an_offline_cluster(): void
    {
        Http::fake([
            'https://pve.example.com:8006/api2/json/nodes' => Http::response(['data' => []], 200),
            'https://pve.example.com:8006/api2/json/cluster/resources*' => Http::response(['data' => []], 200),
            'https://pve.example.com:8006/api2/json/nodes/pve/qemu/9000/config' => Http::response(['data' => []], 200),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No Proxmox nodes are available.');

        Server::eventAddToCart($this->package, ['os_template' => 'ubuntu-24.04']);
    }

    public function test_create_clones_the_template_and_stores_vm_details(): void
    {
        $this->fakeProxmox();

        $order = $this->createOrder();

        (new Server)->create($order, $this->connection);

        $order->refresh();

        $this->assertSame('101', $order->external_id);
        $this->assertSame('pve', $order->data['node']);
        $this->assertSame('vps-'.$order->id, $order->data['hostname']);
        $this->assertSame('ubuntu-24.04', $order->data['template']);
        $this->assertSame(2, $order->data['cores']);
        $this->assertSame(2048, $order->data['memory']);
        $this->assertTrue($order->hasExternalUser());
        $this->assertSame('root', $order->getExternalUser()->username);
        $this->assertNotSame('unknown', $order->getExternalUser()->password);

        $this->assertDatabaseHas('emails', [
            'user_id' => $this->customer->id,
            'identifier' => 'server.proxmox.created',
        ]);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_contains($request->url(), '/qemu/9000/clone'));
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && str_contains($request->url(), '/qemu/101/config'));
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && str_contains($request->url(), '/qemu/101/resize'));
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_contains($request->url(), '/qemu/101/status/start'));
    }

    public function test_create_assigns_the_next_pool_address(): void
    {
        $this->fakeProxmox();

        $this->package->update([
            'data' => array_merge($this->package->data ?? [], [
                'ip_mode' => 'pool',
                'ip_pool' => '10.0.0.20-10.0.0.22',
                'gateway' => '10.0.0.1',
                'cidr' => 24,
            ]),
        ]);

        $existing = $this->createOrder();
        $existing->update([
            'external_id' => '90',
            'data' => ['ipv4' => '10.0.0.20', 'vmid' => 90, 'node' => 'pve'],
        ]);

        $order = $this->createOrder();
        (new Server)->create($order, $this->connection);

        $this->assertSame('10.0.0.21', $order->fresh()->data['ipv4']);
    }

    public function test_suspend_stops_the_vm_and_unsuspend_starts_it(): void
    {
        $this->fakeProxmox(running: true);

        $order = $this->provisionedOrder();
        $server = new Server;

        $server->suspend($order, $this->connection);
        $server->unsuspend($order, $this->connection);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/status/stop'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/status/start'));
    }

    public function test_terminate_deletes_the_vm(): void
    {
        $this->fakeProxmox(running: true);

        (new Server)->terminate($this->provisionedOrder(), $this->connection);

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_contains($request->url(), '/qemu/101'));
    }

    public function test_upgrade_resizes_cpu_memory_and_disk(): void
    {
        $this->fakeProxmox(running: true);

        $newPackage = $this->package->replicate();
        $newPackage->slug = 'ubuntu-kvm-large-'.uniqid();
        $newPackage->name = 'Ubuntu KVM Large '.uniqid();
        $newPackage->data = array_merge($this->package->data ?? [], [
            'cores' => 4,
            'memory' => 4096,
            'disk' => 80,
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

        $this->assertSame(4, $order->fresh()->data['cores']);
        $this->assertSame(4096, $order->fresh()->data['memory']);
        $this->assertSame(80, $order->fresh()->data['disk']);
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && str_contains($request->url(), '/resize'));
    }

    public function test_clients_can_send_power_actions(): void
    {
        $this->fakeProxmox(running: true);

        $order = $this->provisionedOrder();

        Server::actions()->powerAsClient([
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'action' => 'reboot',
        ]);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/status/reboot'));
    }

    public function test_clients_cannot_control_someone_elses_vm(): void
    {
        $this->fakeProxmox();

        $this->expectException(ValidationException::class);

        Server::actions()->powerAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->stranger->id,
            'action' => 'start',
        ]);
    }

    public function test_clients_can_change_the_hostname(): void
    {
        $this->fakeProxmox();

        $order = $this->provisionedOrder();

        Server::actions()->changeHostnameAsClient([
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'hostname' => 'web-01',
        ]);

        $this->assertSame('web-01', $order->fresh()->data['hostname']);
    }

    public function test_clients_can_create_and_restore_snapshots(): void
    {
        $this->fakeProxmox();

        $order = $this->provisionedOrder();

        Server::actions()->createSnapshotAsClient([
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'name' => 'before-upgrade',
            'description' => 'Safe point',
        ]);

        Server::actions()->restoreSnapshotAsClient([
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'name' => 'before-upgrade',
        ]);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_contains($request->url(), '/snapshot') && ! str_contains($request->url(), 'rollback'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/snapshot/before-upgrade/rollback'));
    }

    public function test_reinstall_rebuilds_the_vm_from_a_selected_template(): void
    {
        $this->fakeProxmox(running: true);

        $order = $this->provisionedOrder();

        Server::actions()->reinstallAsClient([
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'os_template' => 'debian-12',
        ]);

        $order->refresh();

        $this->assertSame('101', $order->external_id);
        $this->assertSame('debian-12', $order->data['template']);
        $this->assertSame(9002, $order->data['template_vmid']);
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_contains($request->url(), '/qemu/101'));
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_contains($request->url(), '/qemu/9002/clone'));
    }

    public function test_change_password_updates_cloud_init_and_the_local_account(): void
    {
        $this->fakeProxmox(running: true);

        $order = $this->provisionedOrder();

        (new Server)->changePassword($order, 'NewSecret123!');

        $this->assertSame('NewSecret123!', $order->getExternalUser()->fresh()->password);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/config') && $request->method() === 'PUT');
    }

    public function test_console_returns_a_novnc_url(): void
    {
        $this->fakeProxmox();

        $console = Server::actions()->consoleAsClient([
            'order_id' => $this->provisionedOrder()->id,
            'user_id' => $this->customer->id,
        ]);

        $this->assertStringContainsString('console=kvm', $console['url']);
        $this->assertStringContainsString('vncticket=', $console['url']);
    }

    public function test_uses_proxmox_only_for_proxmox_orders(): void
    {
        $this->assertTrue(Server::usesProxmox($this->provisionedOrder()));
        $this->assertFalse(Server::usesProxmox(null));
    }

    public function test_client_panel_shows_live_usage_cards(): void
    {
        $this->fakeProxmox(running: true);

        $order = $this->provisionedOrder();

        $this->actingAs($this->customer);

        Volt::test('client_area.default.orders.livewire.vm-panel', ['order_id' => $order->id])
            ->assertSee('Virtual machine')
            ->assertSee('Running')
            ->assertSee('CPU')
            ->assertSee('Memory')
            ->assertSee('Disk')
            ->assertSee($order->data['hostname']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function credentials(): array
    {
        return [
            'hostname' => 'https://pve.example.com',
            'port' => 8006,
            'auth_type' => 'token',
            'username' => 'wemx@pve',
            'token_id' => 'billing',
            'token_secret' => '11111111-2222-3333-4444-555555555555',
            'verify_ssl' => '0',
            'default_node' => 'pve',
            'default_storage' => 'local-lvm',
            'default_bridge' => 'vmbr0',
            'vmid_start' => 100,
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
            'external_id' => '101',
            'data' => [
                'vmid' => 101,
                'node' => 'pve',
                'hostname' => 'vps-'.$order->id,
                'name' => 'vps-'.$order->id,
                'template' => 'ubuntu-24.04',
                'template_vmid' => 9000,
                'os_label' => 'Ubuntu 24.04 LTS',
                'username' => 'root',
                'cores' => 2,
                'memory' => 2048,
                'disk' => 40,
                'storage' => 'local-lvm',
                'bridge' => 'vmbr0',
            ],
        ]);

        $order->createExternalUser([
            'external_id' => '101',
            'username' => 'root',
            'password' => 'initial-password',
            'data' => $order->data,
        ]);

        return $order->fresh();
    }

    protected function fakeProxmox(bool $running = false): void
    {
        $state = (object) ['running' => $running];

        Http::fake(function (Request $request) use ($state) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
            $upid = 'UPID:pve:00000000:00000000:00000000:00000000:qmclone:101:wemx@pve:';

            if (str_contains($path, '/status/stop') || str_contains($path, '/status/shutdown')) {
                $state->running = false;
            }

            if (str_contains($path, '/status/start')) {
                $state->running = true;
            }

            if (str_ends_with($path, '/version')) {
                return Http::response(['data' => ['version' => '8.3']], 200);
            }

            if (str_ends_with($path, '/nodes')) {
                return Http::response(['data' => [
                    ['node' => 'pve', 'status' => 'online', 'maxmem' => 32 * 1024 ** 3, 'mem' => 4 * 1024 ** 3],
                ]], 200);
            }

            if (str_contains($path, '/cluster/resources')) {
                return Http::response(['data' => [
                    ['node' => 'pve', 'type' => 'node', 'maxmem' => 32 * 1024 ** 3, 'mem' => 4 * 1024 ** 3, 'maxdisk' => 500 * 1024 ** 3, 'disk' => 20 * 1024 ** 3],
                    ['node' => 'pve', 'type' => 'vm', 'vmid' => 101],
                ]], 200);
            }

            if (str_contains($path, '/cluster/nextid')) {
                return Http::response(['data' => 101], 200);
            }

            if (str_contains($path, '/storage')) {
                return Http::response(['data' => [
                    ['storage' => 'local-lvm', 'content' => 'images'],
                ]], 200);
            }

            if (preg_match('/\/qemu\/\d+\/config$/', $path) && $request->method() === 'GET') {
                return Http::response(['data' => [
                    'scsi0' => 'local-lvm:vm-9000-disk-0,size=10G',
                    'name' => 'ubuntu-template',
                ]], 200);
            }

            if (str_contains($path, '/status/current')) {
                return Http::response(['data' => [
                    'status' => $state->running ? 'running' : 'stopped',
                    'qmpstatus' => $state->running ? 'running' : 'stopped',
                    'cpu' => 0.12,
                    'cpus' => 2,
                    'mem' => 512 * 1024 * 1024,
                    'maxmem' => 2048 * 1024 * 1024,
                    'disk' => 2 * 1024 ** 3,
                    'maxdisk' => 40 * 1024 ** 3,
                    'netin' => 1024,
                    'netout' => 2048,
                    'uptime' => $state->running ? 3600 : 0,
                ]], 200);
            }

            if (str_contains($path, '/tasks/') && str_ends_with($path, '/status')) {
                return Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']], 200);
            }

            if (str_contains($path, '/snapshot') && $request->method() === 'GET') {
                return Http::response(['data' => [
                    ['name' => 'current'],
                    ['name' => 'before-upgrade', 'description' => 'Safe point'],
                ]], 200);
            }

            if (str_contains($path, '/vncproxy')) {
                return Http::response(['data' => [
                    'ticket' => 'PVEVNC-TICKET',
                    'port' => 5900,
                    'user' => 'wemx@pve',
                ]], 200);
            }

            if (in_array($request->method(), ['POST', 'PUT', 'DELETE'], true)) {
                return Http::response(['data' => $upid], 200);
            }

            return Http::response(['data' => []], 200);
        });
    }
}
