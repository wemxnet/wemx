<?php

use App\Models\Order;
use Extensions\Servers\Proxmox\Server;
use Extensions\Servers\Proxmox\Support\OsTemplates;
use Extensions\Servers\Proxmox\Support\ProxmoxVmManager;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component
{
    #[Locked]
    public int $order_id;

    public string $hostname = '';

    public string $os_template = '';

    public string $snapshot_name = '';

    public string $snapshot_description = '';

    public bool $showPassword = false;

    public function mount(): void
    {
        $order = $this->order;

        $this->hostname = (string) ($order?->data['hostname'] ?? '');
        $this->os_template = (string) ($order?->data['template'] ?? $order?->option('os_template', ''));
    }

    #[Computed]
    public function order(): ?Order
    {
        return Order::query()->with(['package.serverConnection', 'user'])->find($this->order_id);
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function usage(): ?array
    {
        $order = $this->order;

        if (! $order || ! Server::usesProxmox($order) || (! $order->external_id && empty($order->data['vmid']))) {
            return null;
        }

        try {
            return ProxmoxVmManager::for($order->package->serverConnection)->status($order);
        } catch (Throwable) {
            return ['error' => true];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function snapshots(): array
    {
        $order = $this->order;

        if (! $order || ! Server::usesProxmox($order) || (int) $order->option('snapshot_limit', 3) <= 0) {
            return [];
        }

        if (! $order->external_id && empty($order->data['vmid'])) {
            return [];
        }

        try {
            return ProxmoxVmManager::for($order->package->serverConnection)->snapshots($order);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function osOptions(): array
    {
        return OsTemplates::options((string) ($this->order?->option('os_templates', '') ?? ''));
    }

    public function refreshUsage(): void
    {
        unset($this->usage, $this->snapshots, $this->order);
    }

    public function power(string $action): void
    {
        Server::actions()->powerAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'action' => $action,
        ]);

        $this->refreshUsage();
        $this->dispatch('toast', type: 'success', message: 'Power action sent to the virtual machine.', title: 'Success');
    }

    public function saveHostname(): void
    {
        Server::actions()->changeHostnameAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'hostname' => $this->hostname,
        ]);

        unset($this->order);
        $this->dispatch('toast', type: 'success', message: 'Hostname updated.', title: 'Success');
    }

    public function reinstall(): void
    {
        Server::actions()->reinstallAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'os_template' => $this->os_template,
        ]);

        $this->refreshUsage();
        $this->dispatch('toast', type: 'success', message: 'The virtual machine is being rebuilt.', title: 'Success');
    }

    public function createSnapshot(): void
    {
        Server::actions()->createSnapshotAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'name' => $this->snapshot_name,
            'description' => $this->snapshot_description,
        ]);

        $this->reset(['snapshot_name', 'snapshot_description']);
        unset($this->snapshots);
        $this->dispatch('toast', type: 'success', message: 'Snapshot created.', title: 'Success');
    }

    public function restoreSnapshot(string $name): void
    {
        Server::actions()->restoreSnapshotAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'name' => $name,
        ]);

        $this->refreshUsage();
        $this->dispatch('toast', type: 'success', message: 'Snapshot restore started.', title: 'Success');
    }

    public function deleteSnapshot(string $name): void
    {
        Server::actions()->deleteSnapshotAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'name' => $name,
        ]);

        unset($this->snapshots);
        $this->dispatch('toast', type: 'success', message: 'Snapshot deleted.', title: 'Success');
    }

    public function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = max($bytes, 0);
        $power = $size > 0 ? min((int) floor(log($size, 1024)), count($units) - 1) : 0;

        return number_format($size / (1024 ** $power), $power === 0 ? 0 : 1).' '.$units[$power];
    }

    public function formatUptime(int $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return "{$days}d {$hours}h";
        }

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }
}

?>

<div wire:poll.15s="refreshUsage">
    @php
        $order = $this->order;
        $usage = $this->usage;
        $canManage = $order && $order->status === 'active';
        $account = $order?->getExternalUser();
        $password = $account?->password;
    @endphp

    @if($order)
        <x-theme::card class="mb-4">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ __('server-proxmox::messages.virtual_machine') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $order->data['os_label'] ?? $order->package->name }}
                        @if(!empty($order->data['hostname']))
                            · {{ $order->data['hostname'] }}
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($usage && empty($usage['error']))
                        @if($usage['running'])
                            <x-theme::badge.success :text="__('server-proxmox::messages.running')" />
                        @else
                            <x-theme::badge.warning :text="__('server-proxmox::messages.stopped')" />
                        @endif
                    @elseif($order->status === 'suspended')
                        <x-theme::badge.warning :text="__('server-proxmox::messages.stopped')" />
                    @else
                        <x-theme::badge.primary :text="__('server-proxmox::messages.unknown')" />
                    @endif

                    @if($canManage && (string) $order->option('allow_console', '1') === '1' && ($order->external_id || !empty($order->data['vmid'])))
                        <x-theme::button.primary :href="route('proxmox.console', $order)" target="_blank" :text="__('server-proxmox::messages.console')" />
                    @endif
                </div>
            </div>

            @if($order->status === 'suspended')
                <x-theme::alert.warning class="mb-4" :text="__('server-proxmox::messages.suspended')" />
            @endif

            @if(!$order->external_id && empty($order->data['vmid']))
                <x-theme::alert.primary :text="__('server-proxmox::messages.not_provisioned')" />
            @elseif(($usage['error'] ?? false) === true)
                <x-theme::alert.warning :text="__('server-proxmox::messages.unavailable')" />
            @else
                <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('server-proxmox::messages.cpu') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $usage['cpu_percent'] ?? 0 }}%</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $usage['cpus'] ?? ($order->data['cores'] ?? 0) }} {{ __('server-proxmox::messages.cores') }}</p>
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div class="h-full rounded-full bg-primary-600" style="width: {{ min(100, $usage['cpu_percent'] ?? 0) }}%"></div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('server-proxmox::messages.memory') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $usage['memory_percent'] ?? 0 }}%</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $this->formatBytes((int) ($usage['memory_used'] ?? 0)) }} / {{ $this->formatBytes((int) ($usage['memory_max'] ?? (($order->data['memory'] ?? 0) * 1024 * 1024))) }}
                        </p>
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div class="h-full rounded-full bg-primary-600" style="width: {{ min(100, $usage['memory_percent'] ?? 0) }}%"></div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('server-proxmox::messages.disk') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $usage['disk_percent'] ?? 0 }}%</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $this->formatBytes((int) ($usage['disk_used'] ?? 0)) }} / {{ $this->formatBytes((int) ($usage['disk_max'] ?? (($order->data['disk'] ?? 0) * 1024 * 1024 * 1024))) }}
                        </p>
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div class="h-full rounded-full bg-primary-600" style="width: {{ min(100, $usage['disk_percent'] ?? 0) }}%"></div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('server-proxmox::messages.network') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $this->formatUptime((int) ($usage['uptime'] ?? 0)) }}</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('server-proxmox::messages.inbound') }} {{ $this->formatBytes((int) ($usage['net_in'] ?? 0)) }}
                            · {{ __('server-proxmox::messages.outbound') }} {{ $this->formatBytes((int) ($usage['net_out'] ?? 0)) }}
                        </p>
                    </div>
                </div>
            @endif

            <x-theme::datagrid.grid :cols="3" :gap="4">
                <x-theme::datagrid.item>
                    <x-slot:label>{{ __('server-proxmox::messages.hostname') }}</x-slot:label>
                    {{ $order->data['hostname'] ?? '—' }}
                </x-theme::datagrid.item>
                <x-theme::datagrid.item>
                    <x-slot:label>{{ __('server-proxmox::messages.ipv4') }}</x-slot:label>
                    {{ $order->data['ipv4'] ?? __('server-proxmox::messages.dhcp') }}
                </x-theme::datagrid.item>
                <x-theme::datagrid.item>
                    <x-slot:label>{{ __('server-proxmox::messages.username') }}</x-slot:label>
                    {{ $account->username ?? ($order->data['username'] ?? 'root') }}
                </x-theme::datagrid.item>
                <x-theme::datagrid.item>
                    <x-slot:label>{{ __('server-proxmox::messages.password') }}</x-slot:label>
                    @if($password)
                        <span class="inline-flex items-center gap-2">
                            <span>{{ $showPassword ? $password : str_repeat('•', 10) }}</span>
                            <button type="button" wire:click="$toggle('showPassword')" class="text-xs text-primary-700 hover:underline dark:text-primary-400">
                                {{ $showPassword ? __('server-proxmox::messages.hide') : __('server-proxmox::messages.show') }}
                            </button>
                        </span>
                    @else
                        —
                    @endif
                </x-theme::datagrid.item>
                <x-theme::datagrid.item>
                    <x-slot:label>{{ __('server-proxmox::messages.vmid') }}</x-slot:label>
                    {{ $order->external_id ?? ($order->data['vmid'] ?? '—') }}
                </x-theme::datagrid.item>
                <x-theme::datagrid.item>
                    <x-slot:label>{{ __('server-proxmox::messages.node') }}</x-slot:label>
                    {{ $order->data['node'] ?? '—' }}
                </x-theme::datagrid.item>
            </x-theme::datagrid.grid>

            @if($canManage && ($order->external_id || !empty($order->data['vmid'])))
                <div class="mt-6 flex flex-wrap gap-2">
                    <x-theme::button.success type="button" wire:click="power('start')" wire:loading.attr="disabled" :text="__('server-proxmox::messages.start')" />
                    <x-theme::button.primary type="button" wire:click="power('shutdown')" wire:confirm="Shut down this virtual machine?" :text="__('server-proxmox::messages.shutdown')" />
                    <x-theme::button.warning type="button" wire:click="power('reboot')" wire:confirm="Reboot this virtual machine?" :text="__('server-proxmox::messages.reboot')" />
                    <x-theme::button.danger type="button" wire:click="power('stop')" wire:confirm="Force stop this virtual machine?" :text="__('server-proxmox::messages.force_stop')" />
                </div>
            @endif
        </x-theme::card>

        @if($canManage && ($order->external_id || !empty($order->data['vmid'])))
            <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-theme::card>
                    <h4 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ __('server-proxmox::messages.change_hostname') }}</h4>
                    <div class="mb-3">
                        <x-theme::form.label for="proxmox-hostname" :text="__('server-proxmox::messages.hostname')" />
                        <x-theme::form.input id="proxmox-hostname" type="text" wire:model="hostname" />
                        @error('hostname')
                            <x-theme::form.error :text="$message" />
                        @enderror
                    </div>
                    <x-theme::button.primary type="button" wire:click="saveHostname" :text="__('server-proxmox::messages.save_hostname')" />
                </x-theme::card>

                @if((string) $order->option('allow_reinstall', '1') === '1')
                    <x-theme::card>
                        <h4 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ __('server-proxmox::messages.reinstall') }}</h4>
                        <div class="mb-3">
                            <x-theme::form.label for="proxmox-os" :text="__('server-proxmox::messages.operating_system')" />
                            <x-theme::form.select id="proxmox-os" wire:model="os_template" :options="$this->osOptions" />
                            @error('os_template')
                                <x-theme::form.error :text="$message" />
                            @enderror
                        </div>
                        <x-theme::button.danger type="button" wire:click="reinstall" wire:confirm="This permanently wipes the virtual machine disks. Continue?" :text="__('server-proxmox::messages.reinstall_server')" />
                    </x-theme::card>
                @endif
            </div>

            @if((int) $order->option('snapshot_limit', 3) > 0)
                <x-theme::card class="mb-4">
                    <h4 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ __('server-proxmox::messages.snapshots') }}</h4>
                    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div>
                            <x-theme::form.label for="snapshot-name" :text="__('server-proxmox::messages.snapshot_name')" />
                            <x-theme::form.input id="snapshot-name" type="text" wire:model="snapshot_name" placeholder="before-upgrade" />
                            @error('name')
                                <x-theme::form.error :text="$message" />
                            @enderror
                        </div>
                        <div class="md:col-span-2">
                            <x-theme::form.label for="snapshot-description" :text="__('server-proxmox::messages.snapshot_description')" />
                            <x-theme::form.input id="snapshot-description" type="text" wire:model="snapshot_description" />
                        </div>
                    </div>
                    <x-theme::button.primary type="button" class="mb-4" wire:click="createSnapshot" :text="__('server-proxmox::messages.create_snapshot')" />

                    @if(count($this->snapshots) === 0)
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('server-proxmox::messages.no_snapshots') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <tr>
                                        <th class="py-2 pr-4">{{ __('server-proxmox::messages.snapshot_name') }}</th>
                                        <th class="py-2 pr-4">{{ __('server-proxmox::messages.snapshot_description') }}</th>
                                        <th class="py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($this->snapshots as $snapshot)
                                        <tr wire:key="snapshot-{{ $snapshot['name'] }}">
                                            <td class="py-3 pr-4 font-medium text-gray-900 dark:text-white">{{ $snapshot['name'] }}</td>
                                            <td class="py-3 pr-4 text-gray-500 dark:text-gray-400">{{ $snapshot['description'] ?? '—' }}</td>
                                            <td class="py-3 text-right">
                                                <div class="flex justify-end gap-2">
                                                    <x-theme::button.warning type="button" wire:click="restoreSnapshot('{{ $snapshot['name'] }}')" wire:confirm="Restore this snapshot?" :text="__('server-proxmox::messages.restore')" />
                                                    <x-theme::button.danger type="button" wire:click="deleteSnapshot('{{ $snapshot['name'] }}')" wire:confirm="Delete this snapshot?" :text="__('server-proxmox::messages.delete')" />
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-theme::card>
            @endif
        @endif
    @endif
</div>
