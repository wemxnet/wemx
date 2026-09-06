<?php

namespace Extensions\Servers\Proxmox\Support;

use App\Models\Order;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\ServerConnection;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProxmoxVmManager
{
    public function __construct(
        protected ProxmoxApi $api,
        protected ServerConnection $connection,
    ) {}

    public static function for(ServerConnection $connection): self
    {
        return new self(ProxmoxApi::fromConnection($connection), $connection);
    }

    /**
     * @param  array<string, mixed>  $configOptions
     */
    public function assertCanProvision(Package $package, array $configOptions = []): void
    {
        $plan = $this->plan($package, $configOptions);
        $template = $this->resolveTemplate($package, $configOptions);
        $node = $this->selectNode($plan['node'], (int) $plan['memory'], (int) $plan['disk']);

        $this->api->qemuConfig($node, $template['vmid']);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(Order $order): array
    {
        $package = $order->package;
        $plan = $this->planFromOrder($order);
        $template = $this->resolveTemplate($package, $this->orderOptions($order));
        $node = $this->selectNode($plan['node'], (int) $plan['memory'], (int) $plan['disk']);
        $vmid = $this->nextVmid();
        $hostname = $this->hostnameFor($order, $plan);
        $password = $this->passwordFor($order, $plan);
        $network = $this->assignNetwork($order, $plan);

        $cloneUpid = $this->api->cloneVm($node, $template['vmid'], [
            'newid' => $vmid,
            'name' => $hostname,
            'full' => (int) $plan['full_clone'],
            'storage' => $plan['storage'],
            'target' => $node,
        ]);

        $this->api->waitForTask(is_string($cloneUpid) ? $cloneUpid : null, $node);

        $this->api->updateConfig($node, $vmid, $this->configPayload($order, $plan, $hostname, $password, $network));

        $this->resizeDiskIfNeeded($node, $vmid, (int) $plan['disk'], $plan['disk_slot']);

        if ((string) $plan['start_after_create'] === '1') {
            $startUpid = $this->api->start($node, $vmid);
            $this->api->waitForTask(is_string($startUpid) ? $startUpid : null, $node, 120);
        }

        return [
            'vmid' => $vmid,
            'node' => $node,
            'name' => $hostname,
            'hostname' => $hostname,
            'template' => $template['id'],
            'template_vmid' => $template['vmid'],
            'os_label' => $template['label'],
            'ipv4' => $network['ipv4'],
            'cidr' => $network['cidr'],
            'gateway' => $network['gateway'],
            'username' => $plan['ci_user'],
            'password' => $password,
            'cores' => (int) $plan['cores'],
            'sockets' => (int) $plan['sockets'],
            'memory' => (int) $plan['memory'],
            'disk' => (int) $plan['disk'],
            'storage' => $plan['storage'],
            'bridge' => $plan['bridge'],
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function suspend(Order $order): void
    {
        [$node, $vmid] = $this->locate($order);

        if ($this->isRunning($node, $vmid)) {
            $upid = $this->api->stop($node, $vmid);
            $this->api->waitForTask(is_string($upid) ? $upid : null, $node, 120);
        }
    }

    public function unsuspend(Order $order): void
    {
        [$node, $vmid] = $this->locate($order);

        if (! $this->isRunning($node, $vmid)) {
            $upid = $this->api->start($node, $vmid);
            $this->api->waitForTask(is_string($upid) ? $upid : null, $node, 120);
        }
    }

    public function terminate(Order $order): void
    {
        [$node, $vmid] = $this->locate($order);

        if ($this->isRunning($node, $vmid)) {
            $stopUpid = $this->api->stop($node, $vmid);
            $this->api->waitForTask(is_string($stopUpid) ? $stopUpid : null, $node, 120);
        }

        $deleteUpid = $this->api->deleteVm($node, $vmid);
        $this->api->waitForTask(is_string($deleteUpid) ? $deleteUpid : null, $node, 180);
    }

    public function upgrade(Order $order, PackagePrice $newPackagePrice): array
    {
        [$node, $vmid] = $this->locate($order);
        $plan = $this->plan($newPackagePrice->package, Arr::only($this->orderOptions($order), [
            'os_template',
            'hostname',
            'ipv4',
            'ssh_keys',
            'node',
        ]));
        $wasRunning = $this->isRunning($node, $vmid);

        if ($wasRunning) {
            $stopUpid = $this->api->shutdown($node, $vmid);
            $this->api->waitForTask(is_string($stopUpid) ? $stopUpid : null, $node, 120);

            if ($this->isRunning($node, $vmid)) {
                $forceUpid = $this->api->stop($node, $vmid);
                $this->api->waitForTask(is_string($forceUpid) ? $forceUpid : null, $node, 60);
            }
        }

        $this->api->updateConfig($node, $vmid, [
            'cores' => (int) $plan['cores'],
            'sockets' => (int) $plan['sockets'],
            'memory' => (int) $plan['memory'],
            'balloon' => (int) $plan['balloon'] > 0 ? (int) $plan['balloon'] : 0,
            'cpu' => $plan['cpu_type'],
        ]);

        $this->resizeDiskIfNeeded($node, $vmid, (int) $plan['disk'], $plan['disk_slot']);

        if ($wasRunning) {
            $startUpid = $this->api->start($node, $vmid);
            $this->api->waitForTask(is_string($startUpid) ? $startUpid : null, $node, 120);
        }

        $data = $order->data ?? [];
        $data['cores'] = (int) $plan['cores'];
        $data['sockets'] = (int) $plan['sockets'];
        $data['memory'] = (int) $plan['memory'];
        $data['disk'] = (int) $plan['disk'];

        return $data;
    }

    public function power(Order $order, string $action): void
    {
        [$node, $vmid] = $this->locate($order);
        $running = $this->isRunning($node, $vmid);

        $upid = match ($action) {
            'start' => $running ? null : $this->api->start($node, $vmid),
            'shutdown' => $running ? $this->api->shutdown($node, $vmid) : null,
            'stop' => $running ? $this->api->stop($node, $vmid) : null,
            'reboot' => $running ? $this->api->reboot($node, $vmid) : null,
            default => throw new Exception("Unsupported power action [{$action}]."),
        };

        $this->api->waitForTask(is_string($upid) ? $upid : null, $node, 120);
    }

    public function changePassword(Order $order, string $password): void
    {
        [$node, $vmid] = $this->locate($order);
        $username = (string) ($order->data['username'] ?? $order->option('ci_user', 'root'));

        $this->api->updateConfig($node, $vmid, [
            'cipassword' => $password,
        ]);

        if ($this->isRunning($node, $vmid)) {
            try {
                $this->api->setUserPassword($node, $vmid, [
                    'username' => $username,
                    'password' => $password,
                ]);
            } catch (Exception) {
                // Cloud-init password is still updated for the next rebuild/reboot.
            }
        }
    }

    public function changeHostname(Order $order, string $hostname): array
    {
        [$node, $vmid] = $this->locate($order);
        $hostname = $this->sanitizeHostname($hostname);

        $this->api->updateConfig($node, $vmid, [
            'name' => $hostname,
        ]);

        $data = $order->data ?? [];
        $data['name'] = $hostname;
        $data['hostname'] = $hostname;

        return $data;
    }

    public function reinstall(Order $order, ?string $templateId = null): array
    {
        $current = $order->data ?? [];
        $preferredVmid = (int) ($order->external_id ?: ($current['vmid'] ?? 0));
        $preferredNode = $current['node'] ?? null;
        $ipv4 = $current['ipv4'] ?? null;

        if ($preferredVmid > 0) {
            try {
                $this->terminate($order);
            } catch (Exception) {
                // Recreate even if the previous VM is already gone.
            }
        }

        $options = $this->orderOptions($order);

        if ($templateId) {
            $options['os_template'] = $templateId;
        }

        $package = $order->package;
        $plan = $this->plan($package, $options);
        $template = $this->resolveTemplate($package, $options);
        $node = $preferredNode ?: $this->selectNode($plan['node'], (int) $plan['memory'], (int) $plan['disk']);
        $vmid = $preferredVmid > 0 ? $preferredVmid : $this->nextVmid();
        $hostname = $current['hostname'] ?? $this->hostnameFor($order, $plan);
        $password = $this->passwordFor($order, $plan);
        $network = $this->assignNetwork($order, $plan, $ipv4);

        $cloneUpid = $this->api->cloneVm($node, $template['vmid'], [
            'newid' => $vmid,
            'name' => $hostname,
            'full' => (int) $plan['full_clone'],
            'storage' => $plan['storage'],
            'target' => $node,
        ]);

        $this->api->waitForTask(is_string($cloneUpid) ? $cloneUpid : null, $node);
        $this->api->updateConfig($node, $vmid, $this->configPayload($order, $plan, $hostname, $password, $network));
        $this->resizeDiskIfNeeded($node, $vmid, (int) $plan['disk'], $plan['disk_slot']);

        if ((string) $plan['start_after_create'] === '1') {
            $startUpid = $this->api->start($node, $vmid);
            $this->api->waitForTask(is_string($startUpid) ? $startUpid : null, $node, 120);
        }

        return [
            'vmid' => $vmid,
            'node' => $node,
            'name' => $hostname,
            'hostname' => $hostname,
            'template' => $template['id'],
            'template_vmid' => $template['vmid'],
            'os_label' => $template['label'],
            'ipv4' => $network['ipv4'],
            'cidr' => $network['cidr'],
            'gateway' => $network['gateway'],
            'username' => $plan['ci_user'],
            'password' => $password,
            'cores' => (int) $plan['cores'],
            'sockets' => (int) $plan['sockets'],
            'memory' => (int) $plan['memory'],
            'disk' => (int) $plan['disk'],
            'storage' => $plan['storage'],
            'bridge' => $plan['bridge'],
            'reinstalled_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(Order $order): array
    {
        [$node, $vmid] = $this->locate($order);
        $status = $this->api->qemuStatus($node, $vmid);
        $maxMem = max(1, (int) ($status['maxmem'] ?? 1));
        $mem = (int) ($status['mem'] ?? 0);
        $maxDisk = max(1, (int) ($status['maxdisk'] ?? 1));
        $disk = (int) ($status['disk'] ?? 0);
        $cpuFraction = (float) ($status['cpu'] ?? 0);
        $cpus = max(1, (int) ($status['cpus'] ?? 1));

        return [
            'vmid' => $vmid,
            'node' => $node,
            'status' => $status['status'] ?? 'unknown',
            'qmpstatus' => $status['qmpstatus'] ?? ($status['status'] ?? 'unknown'),
            'running' => ($status['status'] ?? null) === 'running',
            'uptime' => (int) ($status['uptime'] ?? 0),
            'cpu_percent' => round($cpuFraction * 100, 1),
            'cpus' => $cpus,
            'memory_used' => $mem,
            'memory_max' => $maxMem,
            'memory_percent' => round(($mem / $maxMem) * 100, 1),
            'disk_used' => $disk,
            'disk_max' => $maxDisk,
            'disk_percent' => round(($disk / $maxDisk) * 100, 1),
            'net_in' => (int) ($status['netin'] ?? 0),
            'net_out' => (int) ($status['netout'] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function snapshots(Order $order): array
    {
        [$node, $vmid] = $this->locate($order);

        return collect($this->api->snapshots($node, $vmid) ?? [])
            ->reject(fn ($snapshot) => ($snapshot['name'] ?? '') === 'current')
            ->values()
            ->all();
    }

    public function createSnapshot(Order $order, string $name, ?string $description = null): void
    {
        [$node, $vmid] = $this->locate($order);

        $upid = $this->api->createSnapshot($node, $vmid, array_filter([
            'snapname' => $name,
            'description' => $description,
            'vmstate' => 0,
        ], fn ($value) => $value !== null && $value !== ''));

        $this->api->waitForTask(is_string($upid) ? $upid : null, $node, 180);
    }

    public function deleteSnapshot(Order $order, string $name): void
    {
        [$node, $vmid] = $this->locate($order);

        $upid = $this->api->deleteSnapshot($node, $vmid, $name);
        $this->api->waitForTask(is_string($upid) ? $upid : null, $node, 180);
    }

    public function restoreSnapshot(Order $order, string $name): void
    {
        [$node, $vmid] = $this->locate($order);

        $upid = $this->api->rollbackSnapshot($node, $vmid, $name);
        $this->api->waitForTask(is_string($upid) ? $upid : null, $node, 180);
    }

    /**
     * @return array<string, mixed>
     */
    public function console(Order $order): array
    {
        [$node, $vmid] = $this->locate($order);

        $proxy = $this->api->vncProxy($node, $vmid, [
            'websocket' => 1,
        ]);

        $hostname = rtrim((string) ($this->connection->config['hostname'] ?? ''), '/');
        $port = $this->connection->config['port'] ?? 8006;

        if (! str_starts_with($hostname, 'http://') && ! str_starts_with($hostname, 'https://')) {
            $hostname = 'https://'.$hostname;
        }

        $parts = parse_url($hostname);
        $consoleHost = ($parts['host'] ?? $hostname).':'.($parts['port'] ?? $port);
        $scheme = ($parts['scheme'] ?? 'https') === 'http' ? 'http' : 'https';
        $ticket = $proxy['ticket'] ?? '';

        return [
            'ticket' => $ticket,
            'port' => $proxy['port'] ?? null,
            'user' => $proxy['user'] ?? null,
            'url' => "{$scheme}://{$consoleHost}/?console=kvm&novnc=1&vmid={$vmid}&node={$node}&resize=1&vncticket=".rawurlencode($ticket),
        ];
    }

    /**
     * @return array{0: string, 1: int}
     */
    public function locate(Order $order): array
    {
        $vmid = (int) ($order->external_id ?: ($order->data['vmid'] ?? 0));

        if ($vmid <= 0) {
            throw new Exception('This order does not have a provisioned Proxmox VM.');
        }

        $storedNode = $order->data['node'] ?? null;

        if (is_string($storedNode) && $storedNode !== '') {
            return [$storedNode, $vmid];
        }

        foreach ($this->api->clusterResources('vm') as $resource) {
            if ((int) ($resource['vmid'] ?? 0) === $vmid && ! empty($resource['node'])) {
                return [(string) $resource['node'], $vmid];
            }
        }

        throw new Exception("Could not locate VM {$vmid} on the Proxmox cluster.");
    }

    /**
     * @param  array<string, mixed>  $configOptions
     * @return array<string, mixed>
     */
    public function plan(Package $package, array $configOptions = []): array
    {
        $connection = $this->connection->config ?? [];

        $value = function (string $key, mixed $default = null) use ($package, $configOptions) {
            return Arr::get($configOptions, $key, $package->data($key, $default));
        };

        return [
            'node' => $value('node', $connection['default_node'] ?? ''),
            'storage' => $value('storage', $connection['default_storage'] ?? 'local-lvm'),
            'bridge' => $value('bridge', $connection['default_bridge'] ?? 'vmbr0'),
            'os_templates' => (string) $value('os_templates', ''),
            'os_template' => $value('os_template'),
            'cores' => $value('cores', 1),
            'sockets' => $value('sockets', 1),
            'cpu_type' => $value('cpu_type', 'host'),
            'memory' => $value('memory', 1024),
            'balloon' => $value('balloon', 0),
            'disk' => $value('disk', 20),
            'disk_slot' => $value('disk_slot', 'scsi0'),
            'scsihw' => $value('scsihw', 'virtio-scsi-single'),
            'bios' => $value('bios', 'seabios'),
            'machine' => $value('machine', 'q35'),
            'vlan_tag' => $value('vlan_tag'),
            'rate_limit' => $value('rate_limit'),
            'ip_mode' => $value('ip_mode', 'dhcp'),
            'ip_pool' => $value('ip_pool', $connection['ip_pool'] ?? ''),
            'ipv4' => $value('ipv4'),
            'cidr' => $value('cidr', 24),
            'gateway' => $value('gateway'),
            'nameserver' => $value('nameserver', '1.1.1.1 8.8.8.8'),
            'searchdomain' => $value('searchdomain'),
            'ci_user' => $value('ci_user', 'root'),
            'ci_password' => $value('ci_password'),
            'ssh_keys' => $value('ssh_keys'),
            'hostname_prefix' => $value('hostname_prefix', 'vps-'),
            'hostname' => $value('hostname'),
            'onboot' => $value('onboot', '1'),
            'qemu_agent' => $value('qemu_agent', '1'),
            'start_after_create' => $value('start_after_create', '1'),
            'full_clone' => $value('full_clone', '1'),
            'allow_reinstall' => $value('allow_reinstall', '1'),
            'allow_console' => $value('allow_console', '1'),
            'snapshot_limit' => $value('snapshot_limit', 3),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function planFromOrder(Order $order): array
    {
        return $this->plan($order->package, $this->orderOptions($order));
    }

    /**
     * @return array<string, mixed>
     */
    protected function orderOptions(Order $order): array
    {
        $keys = [
            'node', 'storage', 'bridge', 'os_template', 'cores', 'sockets', 'cpu_type', 'memory',
            'balloon', 'disk', 'disk_slot', 'scsihw', 'bios', 'machine', 'vlan_tag', 'rate_limit',
            'ip_mode', 'ip_pool', 'ipv4', 'cidr', 'gateway', 'nameserver', 'searchdomain',
            'ci_user', 'ci_password', 'ssh_keys', 'hostname_prefix', 'hostname', 'onboot',
            'qemu_agent', 'start_after_create', 'full_clone', 'allow_reinstall', 'allow_console',
            'snapshot_limit',
        ];

        $options = [];

        foreach ($keys as $key) {
            $value = $order->option($key);

            if ($value !== null) {
                $options[$key] = $value;
            }
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $configOptions
     * @return array{id: string, label: string, vmid: int}
     */
    protected function resolveTemplate(Package $package, array $configOptions = []): array
    {
        $raw = (string) Arr::get($configOptions, 'os_templates', $package->data('os_templates', ''));
        $selected = Arr::get($configOptions, 'os_template', $package->data('os_template'));
        $template = OsTemplates::find($raw, $selected);

        if (! $template) {
            throw new Exception('No OS template is configured for this package. Add template VMIDs in the package server settings.');
        }

        return $template;
    }

    protected function selectNode(?string $preferred, int $memoryMb, int $diskGb): string
    {
        $preferred = trim((string) $preferred);
        $nodes = collect($this->api->nodes() ?? [])
            ->filter(fn ($node) => ($node['status'] ?? null) === 'online' || isset($node['node']))
            ->values();

        if ($nodes->isEmpty()) {
            throw new Exception('No Proxmox nodes are available.');
        }

        if ($preferred !== '') {
            $match = $nodes->first(fn ($node) => ($node['node'] ?? null) === $preferred);

            if (! $match) {
                throw new Exception("Proxmox node [{$preferred}] is not available.");
            }

            return $preferred;
        }

        $resources = collect($this->api->clusterResources('node') ?? []);

        $ranked = $nodes->map(function ($node) use ($resources, $memoryMb, $diskGb) {
            $name = $node['node'] ?? null;
            $resource = $resources->firstWhere('node', $name) ?? $node;
            $maxMem = (int) ($resource['maxmem'] ?? 0);
            $usedMem = (int) ($resource['mem'] ?? 0);
            $maxDisk = (int) ($resource['maxdisk'] ?? 0);
            $usedDisk = (int) ($resource['disk'] ?? 0);
            $availableMem = $maxMem > 0 ? $maxMem - $usedMem : PHP_INT_MAX;
            $availableDisk = $maxDisk > 0 ? $maxDisk - $usedDisk : PHP_INT_MAX;

            return [
                'node' => $name,
                'available_mem' => $availableMem,
                'available_disk' => $availableDisk,
                'fits' => $availableMem >= ($memoryMb * 1024 * 1024) && $availableDisk >= ($diskGb * 1024 * 1024 * 1024),
            ];
        })->filter(fn ($node) => $node['node']);

        $fit = $ranked->filter(fn ($node) => $node['fits'])->sortByDesc('available_mem')->first();

        if ($fit) {
            return $fit['node'];
        }

        throw new Exception('No Proxmox node has enough free memory and disk for this virtual machine.');
    }

    protected function nextVmid(): int
    {
        $start = (int) ($this->connection->config['vmid_start'] ?? 100);

        return $this->api->nextId($start > 0 ? $start : null);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    protected function hostnameFor(Order $order, array $plan): string
    {
        $hostname = $plan['hostname'] ?: ($plan['hostname_prefix'].$order->id);

        return $this->sanitizeHostname((string) $hostname);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    protected function passwordFor(Order $order, array $plan): string
    {
        if (! empty($plan['ci_password'])) {
            return (string) $plan['ci_password'];
        }

        $existing = $order->getExternalUser()?->password;

        if (is_string($existing) && $existing !== '' && $existing !== 'unknown') {
            return $existing;
        }

        return Str::password(16);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{ipv4: ?string, cidr: int, gateway: ?string, ipconfig: string}
     */
    protected function assignNetwork(Order $order, array $plan, ?string $preferredIp = null): array
    {
        $mode = $plan['ip_mode'] ?: 'dhcp';
        $cidr = (int) ($plan['cidr'] ?: 24);
        $gateway = $plan['gateway'] ?: null;

        if ($mode === 'dhcp') {
            return [
                'ipv4' => $preferredIp,
                'cidr' => $cidr,
                'gateway' => $gateway,
                'ipconfig' => 'ip=dhcp',
            ];
        }

        $ipv4 = $preferredIp ?: $plan['ipv4'];

        if (! $ipv4 && $mode === 'pool') {
            $ipv4 = IpPool::nextAvailable((string) $plan['ip_pool'], $this->usedIpv4Addresses());
        }

        if (! $ipv4) {
            throw new Exception('No IPv4 address is available for this virtual machine. Add an IP or configure an IP pool.');
        }

        $ipconfig = "ip={$ipv4}/{$cidr}";

        if ($gateway) {
            $ipconfig .= ",gw={$gateway}";
        }

        return [
            'ipv4' => $ipv4,
            'cidr' => $cidr,
            'gateway' => $gateway,
            'ipconfig' => $ipconfig,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function usedIpv4Addresses(): array
    {
        return Order::query()
            ->whereHas('package', fn ($query) => $query->where('connection_id', $this->connection->id))
            ->whereNotNull('data')
            ->get()
            ->pluck('data.ipv4')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array{ipv4: ?string, cidr: int, gateway: ?string, ipconfig: string}  $network
     * @return array<string, mixed>
     */
    protected function configPayload(Order $order, array $plan, string $hostname, string $password, array $network): array
    {
        $net = 'virtio,bridge='.$plan['bridge'];

        if (! empty($plan['vlan_tag'])) {
            $net .= ',tag='.$plan['vlan_tag'];
        }

        if (! empty($plan['rate_limit']) && (int) $plan['rate_limit'] > 0) {
            $net .= ',rate='.$plan['rate_limit'];
        }

        $payload = [
            'name' => $hostname,
            'cores' => (int) $plan['cores'],
            'sockets' => (int) $plan['sockets'],
            'memory' => (int) $plan['memory'],
            'cpu' => $plan['cpu_type'],
            'scsihw' => $plan['scsihw'],
            'bios' => $plan['bios'],
            'machine' => $plan['machine'],
            'onboot' => (int) $plan['onboot'],
            'agent' => ((string) $plan['qemu_agent'] === '1') ? 'enabled=1' : 'enabled=0',
            'ciuser' => $plan['ci_user'],
            'cipassword' => $password,
            'ipconfig0' => $network['ipconfig'],
            'net0' => $net,
        ];

        if ((int) $plan['balloon'] > 0) {
            $payload['balloon'] = (int) $plan['balloon'];
        }

        if (! empty($plan['nameserver'])) {
            $payload['nameserver'] = $plan['nameserver'];
        }

        if (! empty($plan['searchdomain'])) {
            $payload['searchdomain'] = $plan['searchdomain'];
        }

        if (! empty($plan['ssh_keys'])) {
            $payload['sshkeys'] = rawurlencode((string) $plan['ssh_keys']);
        }

        $description = sprintf('WemX order #%d for %s', $order->id, $order->user?->email ?? 'customer');
        $payload['description'] = $description;

        return $payload;
    }

    protected function resizeDiskIfNeeded(string $node, int $vmid, int $diskGb, string $slot): void
    {
        if ($diskGb <= 0) {
            return;
        }

        $config = $this->api->qemuConfig($node, $vmid);
        $disk = $config[$slot] ?? null;

        if (! is_string($disk)) {
            return;
        }

        if (preg_match('/size=(\d+)([KMGT])/i', $disk, $matches)) {
            $currentGb = match (strtoupper($matches[2])) {
                'T' => (int) $matches[1] * 1024,
                'G' => (int) $matches[1],
                'M' => (int) ceil(((int) $matches[1]) / 1024),
                default => 0,
            };

            if ($currentGb >= $diskGb) {
                return;
            }
        }

        $this->api->resizeDisk($node, $vmid, [
            'disk' => $slot,
            'size' => $diskGb.'G',
        ]);
    }

    protected function isRunning(string $node, int $vmid): bool
    {
        $status = $this->api->qemuStatus($node, $vmid);

        return ($status['status'] ?? null) === 'running';
    }

    protected function sanitizeHostname(string $hostname): string
    {
        $hostname = strtolower(trim($hostname));
        $hostname = preg_replace('/[^a-z0-9-]+/', '-', $hostname) ?: 'vps';
        $hostname = trim($hostname, '-');

        if ($hostname === '') {
            $hostname = 'vps';
        }

        return Str::limit($hostname, 63, '');
    }
}
