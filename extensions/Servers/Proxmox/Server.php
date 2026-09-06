<?php

namespace Extensions\Servers\Proxmox;

use App\Extensions\Foundation\ServerExtension;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\ServerConnection;
use Exception;
use Extensions\Servers\Proxmox\Actions\ProxmoxVmActions;
use Extensions\Servers\Proxmox\Support\OsTemplates;
use Extensions\Servers\Proxmox\Support\ProxmoxApi;
use Extensions\Servers\Proxmox\Support\ProxmoxVmManager;
use Illuminate\Support\Facades\Cache;

class Server extends ServerExtension
{
    protected string $id = 'server-proxmox';

    protected string $name = 'Proxmox VE';

    protected string $description = 'Resell KVM virtual machines from Proxmox VE with OS templates, cloud-init, resource limits, and a client control panel.';

    protected string $type = 'Server';

    protected string $icon = 'server';

    protected string $version = '1.0.0';

    protected array $wemxVersions = ['*'];

    protected array $authors = [
        [
            'name' => 'WemX',
            'email' => 'mubeen@wemx.net',
        ],
    ];

    public function providers(): array
    {
        return [];
    }

    public function elements(): array
    {
        return [
            [
                'element' => 'client-order-top-view',
                'view' => 'server-proxmox::client_area.default.orders.widgets.vm-panel',
            ],
            [
                'element' => 'admin-order-sidebar-view',
                'view' => 'server-proxmox::admin_area.default.orders.widgets.vm-sidebar',
            ],
        ];
    }

    public function setConfig(): array
    {
        $doesNotEndWithSlash = function ($attribute, $value, $fail) {
            if (is_string($value) && preg_match('/\/$/', $value)) {
                $fail('Hostname must not end with a slash. Use https://pve.example.com');
            }
        };

        return [
            [
                'key' => 'hostname',
                'name' => 'Hostname',
                'description' => 'Proxmox host without a trailing slash, for example https://pve.example.com',
                'type' => 'text',
                'default_value' => 'https://pve.example.com',
                'rules' => ['required', 'string', $doesNotEndWithSlash],
            ],
            [
                'key' => 'port',
                'name' => 'Port',
                'description' => 'API port. Default is 8006.',
                'type' => 'number',
                'default_value' => 8006,
                'rules' => ['required', 'numeric', 'min:1', 'max:65535'],
            ],
            [
                'key' => 'auth_type',
                'name' => 'Authentication',
                'description' => 'API tokens are recommended for billing automation.',
                'type' => 'select',
                'options' => [
                    'token' => 'API token',
                    'password' => 'Username and password',
                ],
                'default_value' => 'token',
                'rules' => ['required', 'in:token,password'],
            ],
            [
                'key' => 'username',
                'name' => 'Username',
                'description' => 'Proxmox user including the realm, for example root@pam or wemx@pve',
                'type' => 'text',
                'default_value' => 'root@pam',
                'rules' => ['required', 'string'],
            ],
            [
                'key' => 'token_id',
                'name' => 'Token ID',
                'description' => 'API token name. Required when using token authentication.',
                'type' => 'text',
                'rules' => ['nullable', 'string'],
            ],
            [
                'key' => 'token_secret',
                'name' => 'Token secret',
                'description' => 'API token UUID. Required when using token authentication.',
                'type' => 'password',
                'rules' => ['nullable', 'string'],
            ],
            [
                'key' => 'password',
                'name' => 'Password',
                'description' => 'Required only when using username and password authentication.',
                'type' => 'password',
                'rules' => ['nullable', 'string'],
            ],
            [
                'key' => 'verify_ssl',
                'name' => 'Verify SSL',
                'description' => 'Disable this for self-signed certificates.',
                'type' => 'select',
                'options' => [
                    '0' => 'Disabled',
                    '1' => 'Enabled',
                ],
                'default_value' => '0',
                'rules' => ['required', 'in:0,1'],
            ],
            [
                'key' => 'default_node',
                'name' => 'Default node',
                'description' => 'Optional. Leave empty to place VMs on the node with the most free memory.',
                'type' => 'text',
                'rules' => ['nullable', 'string'],
            ],
            [
                'key' => 'default_storage',
                'name' => 'Default storage',
                'description' => 'Default disk storage, for example local-lvm or local-zfs.',
                'type' => 'text',
                'default_value' => 'local-lvm',
                'rules' => ['required', 'string'],
            ],
            [
                'key' => 'default_bridge',
                'name' => 'Default bridge',
                'description' => 'Default virtual network bridge, usually vmbr0.',
                'type' => 'text',
                'default_value' => 'vmbr0',
                'rules' => ['required', 'string'],
            ],
            [
                'key' => 'ip_pool',
                'name' => 'IPv4 pool',
                'description' => 'Optional fallback addresses used when a package is set to pool mode. One IP or range per line, for example 192.168.1.10-192.168.1.20',
                'type' => 'textarea',
                'rules' => ['nullable', 'string'],
            ],
            [
                'key' => 'vmid_start',
                'name' => 'VMID start',
                'description' => 'Lowest VMID WemX should request from the cluster.',
                'type' => 'number',
                'default_value' => 100,
                'rules' => ['required', 'numeric', 'min:100'],
            ],
            [
                'key' => 'debug_mode',
                'name' => 'Debug mode',
                'description' => 'Include API endpoint details in error messages. Keep disabled in production.',
                'type' => 'select',
                'options' => [
                    '0' => 'Disabled',
                    '1' => 'Enabled',
                ],
                'default_value' => '0',
                'rules' => ['required', 'in:0,1'],
            ],
        ];
    }

    public function setPackageConfig(Package $package, ServerConnection $connection): array
    {
        $config = $connection->config ?? [];
        $nodeOptions = $this->cachedNodeOptions($connection);
        $storageOptions = $this->cachedStorageOptions($connection, $package->data('node') ?: ($config['default_node'] ?? null));
        $osOptions = OsTemplates::options((string) $package->data('os_templates', $this->defaultOsTemplates()));

        $nodeField = $nodeOptions === []
            ? [
                'key' => 'node',
                'name' => 'Node',
                'col' => 'col-4',
                'description' => 'Proxmox node to deploy on. Leave empty to auto-select the node with the most free memory.',
                'type' => 'text',
                'default_value' => $config['default_node'] ?? '',
                'rules' => ['nullable', 'string'],
                'is_configurable' => true,
            ]
            : [
                'key' => 'node',
                'name' => 'Node',
                'col' => 'col-4',
                'description' => 'Proxmox node to deploy on. Leave empty to auto-select.',
                'type' => 'select',
                'options' => ['' => 'Auto-select'] + $nodeOptions,
                'default_value' => $config['default_node'] ?? '',
                'rules' => ['nullable', 'string'],
                'is_configurable' => true,
            ];

        $storageField = $storageOptions === []
            ? [
                'key' => 'storage',
                'name' => 'Storage',
                'col' => 'col-4',
                'description' => 'Disk storage for cloned virtual machines.',
                'type' => 'text',
                'default_value' => $config['default_storage'] ?? 'local-lvm',
                'rules' => ['required', 'string'],
                'is_configurable' => false,
            ]
            : [
                'key' => 'storage',
                'name' => 'Storage',
                'col' => 'col-4',
                'description' => 'Disk storage for cloned virtual machines.',
                'type' => 'select',
                'options' => $storageOptions,
                'default_value' => $config['default_storage'] ?? array_key_first($storageOptions),
                'rules' => ['required', 'string'],
                'is_configurable' => false,
            ];

        $osField = $osOptions === []
            ? [
                'key' => 'os_template',
                'name' => 'Default OS template',
                'col' => 'col-4',
                'description' => 'Template VMID used when the customer does not choose an operating system.',
                'type' => 'text',
                'rules' => ['required'],
                'is_configurable' => true,
            ]
            : [
                'key' => 'os_template',
                'name' => 'Default OS',
                'col' => 'col-4',
                'description' => 'Default operating system cloned for new servers. Make this configurable so customers can pick Ubuntu, Debian, and other templates.',
                'type' => 'select',
                'options' => $osOptions,
                'default_value' => array_key_first($osOptions),
                'rules' => ['required'],
                'is_configurable' => true,
            ];

        return [
            [
                'key' => 'os_templates',
                'name' => 'OS templates',
                'col' => 'col-12',
                'description' => 'One template per line using id|Label|vmid. Example: ubuntu-24.04|Ubuntu 24.04 LTS|9000',
                'type' => 'textarea',
                'default_value' => $this->defaultOsTemplates(),
                'rules' => ['required', 'string'],
                'is_configurable' => false,
            ],
            $osField,
            $nodeField,
            $storageField,
            [
                'key' => 'bridge',
                'name' => 'Network bridge',
                'col' => 'col-4',
                'description' => 'Virtual bridge attached to net0.',
                'type' => 'text',
                'default_value' => $config['default_bridge'] ?? 'vmbr0',
                'rules' => ['required', 'string'],
                'is_configurable' => false,
            ],
            [
                'key' => 'cores',
                'name' => 'CPU cores',
                'col' => 'col-4',
                'description' => 'vCPU cores assigned to the virtual machine.',
                'type' => 'number',
                'default_value' => 2,
                'min' => 1,
                'rules' => ['required', 'numeric', 'min:1', 'max:128'],
                'is_configurable' => true,
            ],
            [
                'key' => 'sockets',
                'name' => 'CPU sockets',
                'col' => 'col-4',
                'description' => 'Usually 1 unless you are matching physical socket layouts.',
                'type' => 'number',
                'default_value' => 1,
                'min' => 1,
                'rules' => ['required', 'numeric', 'min:1', 'max:8'],
                'is_configurable' => false,
            ],
            [
                'key' => 'cpu_type',
                'name' => 'CPU type',
                'col' => 'col-4',
                'description' => 'host is fastest. Use x86-64-v2-AES for live migration across mixed hosts.',
                'type' => 'select',
                'options' => [
                    'host' => 'host (best performance)',
                    'kvm64' => 'kvm64',
                    'x86-64-v2-AES' => 'x86-64-v2-AES',
                    'x86-64-v3' => 'x86-64-v3',
                ],
                'default_value' => 'host',
                'rules' => ['required', 'string'],
                'is_configurable' => false,
            ],
            [
                'key' => 'memory',
                'name' => 'Memory (MB)',
                'col' => 'col-4',
                'description' => 'RAM in megabytes. 2048 = 2 GB, 4096 = 4 GB.',
                'type' => 'number',
                'default_value' => 2048,
                'min' => 256,
                'rules' => ['required', 'numeric', 'min:256', 'max:1048576'],
                'is_configurable' => true,
            ],
            [
                'key' => 'balloon',
                'name' => 'Balloon (MB)',
                'col' => 'col-4',
                'description' => 'Minimum balloon memory. Set 0 to disable memory ballooning.',
                'type' => 'number',
                'default_value' => 0,
                'min' => 0,
                'rules' => ['required', 'numeric', 'min:0'],
                'is_configurable' => false,
            ],
            [
                'key' => 'disk',
                'name' => 'Disk (GB)',
                'col' => 'col-4',
                'description' => 'System disk size after clone. The template disk is expanded to this size.',
                'type' => 'number',
                'default_value' => 40,
                'min' => 5,
                'rules' => ['required', 'numeric', 'min:5', 'max:16384'],
                'is_configurable' => true,
            ],
            [
                'key' => 'disk_slot',
                'name' => 'Disk slot',
                'col' => 'col-4',
                'description' => 'Disk to resize on the template, usually scsi0.',
                'type' => 'select',
                'options' => [
                    'scsi0' => 'scsi0',
                    'virtio0' => 'virtio0',
                    'sata0' => 'sata0',
                    'ide0' => 'ide0',
                ],
                'default_value' => 'scsi0',
                'rules' => ['required', 'in:scsi0,virtio0,sata0,ide0'],
                'is_configurable' => false,
            ],
            [
                'key' => 'scsihw',
                'name' => 'SCSI controller',
                'col' => 'col-4',
                'description' => 'Controller used by modern Linux cloud images.',
                'type' => 'select',
                'options' => [
                    'virtio-scsi-single' => 'virtio-scsi-single',
                    'virtio-scsi-pci' => 'virtio-scsi-pci',
                    'lsi' => 'lsi',
                ],
                'default_value' => 'virtio-scsi-single',
                'rules' => ['required', 'string'],
                'is_configurable' => false,
            ],
            [
                'key' => 'bios',
                'name' => 'BIOS',
                'col' => 'col-4',
                'description' => 'Use OVMF for UEFI templates.',
                'type' => 'select',
                'options' => [
                    'seabios' => 'SeaBIOS',
                    'ovmf' => 'OVMF (UEFI)',
                ],
                'default_value' => 'seabios',
                'rules' => ['required', 'in:seabios,ovmf'],
                'is_configurable' => false,
            ],
            [
                'key' => 'machine',
                'name' => 'Machine type',
                'col' => 'col-4',
                'description' => 'q35 is recommended for current Linux distributions.',
                'type' => 'select',
                'options' => [
                    'q35' => 'q35',
                    'i440fx' => 'i440fx',
                ],
                'default_value' => 'q35',
                'rules' => ['required', 'in:q35,i440fx'],
                'is_configurable' => false,
            ],
            [
                'key' => 'vlan_tag',
                'name' => 'VLAN tag',
                'col' => 'col-4',
                'description' => 'Optional 802.1Q tag for the primary NIC.',
                'type' => 'number',
                'rules' => ['nullable', 'numeric', 'min:1', 'max:4094'],
                'is_configurable' => false,
            ],
            [
                'key' => 'rate_limit',
                'name' => 'Network rate (MB/s)',
                'col' => 'col-4',
                'description' => 'Optional outbound rate limit in megabytes per second. Leave empty for unlimited.',
                'type' => 'number',
                'rules' => ['nullable', 'numeric', 'min:1'],
                'is_configurable' => true,
            ],
            [
                'key' => 'ip_mode',
                'name' => 'IPv4 mode',
                'col' => 'col-4',
                'description' => 'DHCP uses cloud-init DHCP. Pool assigns the next free address from the package or connection pool.',
                'type' => 'select',
                'options' => [
                    'dhcp' => 'DHCP',
                    'pool' => 'IP pool',
                    'static' => 'Static IP',
                ],
                'default_value' => 'dhcp',
                'rules' => ['required', 'in:dhcp,pool,static'],
                'is_configurable' => false,
            ],
            [
                'key' => 'ip_pool',
                'name' => 'Package IPv4 pool',
                'col' => 'col-8',
                'description' => 'Used when IPv4 mode is pool. One address or range per line.',
                'type' => 'textarea',
                'rules' => ['nullable', 'string'],
                'is_configurable' => false,
            ],
            [
                'key' => 'ipv4',
                'name' => 'Static IPv4',
                'col' => 'col-4',
                'description' => 'Used when IPv4 mode is static. Customers can also supply this as a configurable option.',
                'type' => 'text',
                'rules' => ['nullable', 'ipv4'],
                'is_configurable' => true,
            ],
            [
                'key' => 'cidr',
                'name' => 'CIDR prefix',
                'col' => 'col-4',
                'description' => 'Prefix length for static and pool assignments, usually 24.',
                'type' => 'number',
                'default_value' => 24,
                'rules' => ['required', 'numeric', 'min:8', 'max:32'],
                'is_configurable' => false,
            ],
            [
                'key' => 'gateway',
                'name' => 'Gateway',
                'col' => 'col-4',
                'description' => 'IPv4 gateway for static and pool assignments.',
                'type' => 'text',
                'rules' => ['nullable', 'ipv4'],
                'is_configurable' => false,
            ],
            [
                'key' => 'nameserver',
                'name' => 'Nameservers',
                'col' => 'col-4',
                'description' => 'Cloud-init DNS servers, space separated.',
                'type' => 'text',
                'default_value' => '1.1.1.1 8.8.8.8',
                'rules' => ['nullable', 'string'],
                'is_configurable' => false,
            ],
            [
                'key' => 'searchdomain',
                'name' => 'Search domain',
                'col' => 'col-4',
                'description' => 'Optional cloud-init search domain.',
                'type' => 'text',
                'rules' => ['nullable', 'string'],
                'is_configurable' => false,
            ],
            [
                'key' => 'ci_user',
                'name' => 'Cloud-init user',
                'col' => 'col-4',
                'description' => 'Primary login user created by cloud-init.',
                'type' => 'text',
                'default_value' => 'root',
                'rules' => ['required', 'string'],
                'is_configurable' => false,
            ],
            [
                'key' => 'ssh_keys',
                'name' => 'SSH public keys',
                'col' => 'col-8',
                'description' => 'Optional public keys injected into every VM. One key per line.',
                'type' => 'textarea',
                'rules' => ['nullable', 'string'],
                'is_configurable' => true,
            ],
            [
                'key' => 'hostname_prefix',
                'name' => 'Hostname prefix',
                'col' => 'col-4',
                'description' => 'Used when the customer does not provide a hostname. The order ID is appended.',
                'type' => 'text',
                'default_value' => 'vps-',
                'rules' => ['required', 'string'],
                'is_configurable' => false,
            ],
            [
                'key' => 'hostname',
                'name' => 'Hostname',
                'col' => 'col-4',
                'description' => 'Optional fixed hostname. Make this configurable to let customers name their server.',
                'type' => 'text',
                'rules' => ['nullable', 'string', 'max:63'],
                'is_configurable' => true,
            ],
            [
                'key' => 'onboot',
                'name' => 'Start on boot',
                'col' => 'col-4',
                'description' => 'Start the VM when the Proxmox node boots.',
                'type' => 'select',
                'options' => [
                    '1' => 'Enabled',
                    '0' => 'Disabled',
                ],
                'default_value' => '1',
                'rules' => ['required', 'in:0,1'],
                'is_configurable' => false,
            ],
            [
                'key' => 'qemu_agent',
                'name' => 'QEMU guest agent',
                'col' => 'col-4',
                'description' => 'Enable the guest agent for better IP and password support.',
                'type' => 'select',
                'options' => [
                    '1' => 'Enabled',
                    '0' => 'Disabled',
                ],
                'default_value' => '1',
                'rules' => ['required', 'in:0,1'],
                'is_configurable' => false,
            ],
            [
                'key' => 'start_after_create',
                'name' => 'Start after create',
                'col' => 'col-4',
                'description' => 'Power the VM on as soon as provisioning finishes.',
                'type' => 'select',
                'options' => [
                    '1' => 'Enabled',
                    '0' => 'Disabled',
                ],
                'default_value' => '1',
                'rules' => ['required', 'in:0,1'],
                'is_configurable' => false,
            ],
            [
                'key' => 'full_clone',
                'name' => 'Full clone',
                'col' => 'col-4',
                'description' => 'Full clones are independent of the template. Linked clones are faster but keep a dependency on the template.',
                'type' => 'select',
                'options' => [
                    '1' => 'Full clone',
                    '0' => 'Linked clone',
                ],
                'default_value' => '1',
                'rules' => ['required', 'in:0,1'],
                'is_configurable' => false,
            ],
            [
                'key' => 'allow_reinstall',
                'name' => 'Allow reinstall',
                'col' => 'col-4',
                'description' => 'Let customers wipe and rebuild the VM from a template.',
                'type' => 'select',
                'options' => [
                    '1' => 'Enabled',
                    '0' => 'Disabled',
                ],
                'default_value' => '1',
                'rules' => ['required', 'in:0,1'],
                'is_configurable' => false,
            ],
            [
                'key' => 'allow_console',
                'name' => 'Allow console',
                'col' => 'col-4',
                'description' => 'Let customers open a noVNC console through Proxmox.',
                'type' => 'select',
                'options' => [
                    '1' => 'Enabled',
                    '0' => 'Disabled',
                ],
                'default_value' => '1',
                'rules' => ['required', 'in:0,1'],
                'is_configurable' => false,
            ],
            [
                'key' => 'snapshot_limit',
                'name' => 'Snapshot limit',
                'col' => 'col-4',
                'description' => 'Maximum customer snapshots. Set 0 to disable snapshots.',
                'type' => 'number',
                'default_value' => 3,
                'min' => 0,
                'rules' => ['required', 'numeric', 'min:0', 'max:50'],
                'is_configurable' => true,
            ],
        ];
    }

    public static function testConnection(array $credentials): string
    {
        $version = ProxmoxApi::make($credentials)->version();
        $release = $version['version'] ?? $version['release'] ?? 'unknown';

        return "Connected to Proxmox VE {$release}.";
    }

    /**
     * @param  array<string, mixed>  $configOptions
     */
    public static function eventAddToCart(Package $package, $configOptions = []): void
    {
        ProxmoxVmManager::for($package->serverConnection)->assertCanProvision($package, is_array($configOptions) ? $configOptions : []);
    }

    public function create(Order $order, ServerConnection $connection): void
    {
        $data = ProxmoxVmManager::for($connection)->create($order);

        self::actions()->storeProvisionedState($order, $data);

        $order->user->email([
            'identifier' => 'server.proxmox.created',
            'mailable_type' => Order::class,
            'mailable_id' => $order->id,
            'variables' => [
                'hostname' => $data['hostname'],
                'ipv4' => $data['ipv4'] ?? 'DHCP',
                'username' => $data['username'],
                'password' => $data['password'],
                'os_label' => $data['os_label'] ?? '',
            ],
            'button' => [
                'url' => route('orders.view', $order->id),
            ],
        ]);
    }

    public function suspend(Order $order, ServerConnection $connection): void
    {
        ProxmoxVmManager::for($connection)->suspend($order);
    }

    public function unsuspend(Order $order, ServerConnection $connection): void
    {
        ProxmoxVmManager::for($connection)->unsuspend($order);
    }

    public function terminate(Order $order, ServerConnection $connection): void
    {
        ProxmoxVmManager::for($connection)->terminate($order);
    }

    public function upgradeOrDowngrade(Order $order, PackagePrice $oldPackagePrice, PackagePrice $newPackagePrice, ServerConnection $connection): void
    {
        $data = ProxmoxVmManager::for($connection)->upgrade($order, $newPackagePrice);

        $order->update(['data' => $data]);
    }

    public function changePassword(Order $order, string $newPassword): void
    {
        ProxmoxVmManager::for($order->package->serverConnection)->changePassword($order, $newPassword);

        $order->updateExternalPassword($newPassword);
    }

    public static function actions(): ProxmoxVmActions
    {
        return new ProxmoxVmActions;
    }

    public static function usesProxmox(?Order $order): bool
    {
        return $order?->package?->serverConnection?->extension_identifier === 'server-proxmox';
    }

    protected function defaultOsTemplates(): string
    {
        return implode("\n", [
            'ubuntu-24.04|Ubuntu 24.04 LTS|9000',
            'ubuntu-22.04|Ubuntu 22.04 LTS|9001',
            'debian-12|Debian 12 Bookworm|9002',
            'debian-11|Debian 11 Bullseye|9003',
            'almalinux-9|AlmaLinux 9|9004',
            'rocky-9|Rocky Linux 9|9005',
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function cachedNodeOptions(ServerConnection $connection): array
    {
        $connectionId = $connection->id ?? 'new';

        try {
            return Cache::remember("proxmox:nodes:{$connectionId}", now()->addMinutes(10), function () use ($connection) {
                $nodes = ProxmoxApi::fromConnection($connection)->nodes();

                return collect($nodes)
                    ->mapWithKeys(function ($node) {
                        $name = $node['node'] ?? null;

                        return $name ? [$name => $name] : [];
                    })
                    ->all();
            });
        } catch (Exception) {
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    protected function cachedStorageOptions(ServerConnection $connection, mixed $node): array
    {
        $node = is_string($node) && $node !== '' ? $node : null;
        $connectionId = $connection->id ?? 'new';

        try {
            return Cache::remember("proxmox:storage:{$connectionId}:".($node ?? 'all'), now()->addMinutes(10), function () use ($connection, $node) {
                $storages = ProxmoxApi::fromConnection($connection)->storages($node, 'images');

                return collect($storages)
                    ->mapWithKeys(function ($storage) {
                        $name = $storage['storage'] ?? null;

                        return $name ? [$name => $name] : [];
                    })
                    ->all();
            });
        } catch (Exception) {
            return [];
        }
    }
}
