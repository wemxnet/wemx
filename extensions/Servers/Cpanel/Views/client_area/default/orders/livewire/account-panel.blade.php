<?php

use App\Models\Order;
use Extensions\Servers\Cpanel\Server;
use Extensions\Servers\Cpanel\Support\CpanelAccountManager;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component
{
    #[Locked]
    public int $order_id;

    public string $tab = 'overview';

    public bool $showPassword = false;

    public string $password = '';

    public string $email_local = '';

    public string $email_password = '';

    public string $email_quota = '0';

    public string $ftp_user = '';

    public string $ftp_password = '';

    public string $ftp_quota = '0';

    public string $ftp_homedir = '';

    public string $database_name = '';

    public string $database_user = '';

    public string $database_user_password = '';

    public string $grant_user = '';

    public string $grant_database = '';

    public string $addon_domain = '';

    public string $addon_subdomain = '';

    public string $subdomain = '';

    public string $parked_domain = '';

    #[Computed]
    public function order(): ?Order
    {
        return Order::query()->with(['package.serverConnection', 'user'])->find($this->order_id);
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function summary(): ?array
    {
        $order = $this->order;

        if (! $order || ! Server::usesCpanel($order) || (! $order->external_id && empty($order->data['username']))) {
            return null;
        }

        try {
            return CpanelAccountManager::for($order->package->serverConnection)->summary($order);
        } catch (Throwable) {
            return ['error' => true];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function emails(): array
    {
        return $this->toolList('allow_email', fn (CpanelAccountManager $manager, Order $order) => $manager->emailAccounts($order));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function ftpAccounts(): array
    {
        return $this->toolList('allow_ftp', fn (CpanelAccountManager $manager, Order $order) => $manager->ftpAccounts($order));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function databases(): array
    {
        return $this->toolList('allow_databases', fn (CpanelAccountManager $manager, Order $order) => $manager->databases($order));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function databaseUsers(): array
    {
        return $this->toolList('allow_databases', fn (CpanelAccountManager $manager, Order $order) => $manager->databaseUsers($order));
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function domains(): array
    {
        $order = $this->readyOrder('allow_domains');

        if (! $order) {
            return [];
        }

        try {
            return CpanelAccountManager::for($order->package->serverConnection)->domains($order);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function sslHosts(): array
    {
        return $this->toolList('allow_ssl', fn (CpanelAccountManager $manager, Order $order) => $manager->sslHosts($order));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function backups(): array
    {
        return $this->toolList('allow_backups', fn (CpanelAccountManager $manager, Order $order) => $manager->backups($order));
    }

    public function refreshPanel(): void
    {
        unset(
            $this->order,
            $this->summary,
            $this->emails,
            $this->ftpAccounts,
            $this->databases,
            $this->databaseUsers,
            $this->domains,
            $this->sslHosts,
            $this->backups,
        );
    }

    public function changePassword(): void
    {
        Server::actions()->changePasswordAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'password' => $this->password,
        ]);

        $this->reset('password');
        unset($this->order);
        $this->dispatch('toast', type: 'success', message: 'cPanel password updated.', title: 'Success');
    }

    public function createEmail(): void
    {
        Server::actions()->createEmailAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'email' => $this->email_local,
            'password' => $this->email_password,
            'quota' => $this->email_quota,
        ]);

        $this->reset(['email_local', 'email_password', 'email_quota']);
        unset($this->emails);
        $this->dispatch('toast', type: 'success', message: 'Email account created.', title: 'Success');
    }

    public function deleteEmail(string $email): void
    {
        Server::actions()->deleteEmailAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'email' => $email,
        ]);

        unset($this->emails);
        $this->dispatch('toast', type: 'success', message: 'Email account deleted.', title: 'Success');
    }

    public function createFtp(): void
    {
        Server::actions()->createFtpAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'user' => $this->ftp_user,
            'password' => $this->ftp_password,
            'quota' => $this->ftp_quota,
            'homedir' => $this->ftp_homedir,
        ]);

        $this->reset(['ftp_user', 'ftp_password', 'ftp_quota', 'ftp_homedir']);
        unset($this->ftpAccounts);
        $this->dispatch('toast', type: 'success', message: 'FTP account created.', title: 'Success');
    }

    public function deleteFtp(string $user): void
    {
        Server::actions()->deleteFtpAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'user' => $user,
        ]);

        unset($this->ftpAccounts);
        $this->dispatch('toast', type: 'success', message: 'FTP account deleted.', title: 'Success');
    }

    public function createDatabase(): void
    {
        Server::actions()->createDatabaseAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'name' => $this->database_name,
        ]);

        $this->reset('database_name');
        unset($this->databases);
        $this->dispatch('toast', type: 'success', message: 'Database created.', title: 'Success');
    }

    public function deleteDatabase(string $name): void
    {
        Server::actions()->deleteDatabaseAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'name' => $name,
        ]);

        unset($this->databases);
        $this->dispatch('toast', type: 'success', message: 'Database deleted.', title: 'Success');
    }

    public function createDatabaseUser(): void
    {
        Server::actions()->createDatabaseUserAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'name' => $this->database_user,
            'password' => $this->database_user_password,
        ]);

        $this->reset(['database_user', 'database_user_password']);
        unset($this->databaseUsers);
        $this->dispatch('toast', type: 'success', message: 'Database user created.', title: 'Success');
    }

    public function deleteDatabaseUser(string $name): void
    {
        Server::actions()->deleteDatabaseUserAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'name' => $name,
        ]);

        unset($this->databaseUsers);
        $this->dispatch('toast', type: 'success', message: 'Database user deleted.', title: 'Success');
    }

    public function grantPrivileges(): void
    {
        Server::actions()->grantDatabasePrivilegesAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'user' => $this->grant_user,
            'database' => $this->grant_database,
        ]);

        $this->dispatch('toast', type: 'success', message: 'Privileges granted.', title: 'Success');
    }

    public function createAddonDomain(): void
    {
        Server::actions()->createAddonDomainAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'domain' => $this->addon_domain,
            'subdomain' => $this->addon_subdomain,
        ]);

        $this->reset(['addon_domain', 'addon_subdomain']);
        unset($this->domains);
        $this->dispatch('toast', type: 'success', message: 'Addon domain created.', title: 'Success');
    }

    public function deleteAddonDomain(string $domain, string $subdomain): void
    {
        Server::actions()->deleteAddonDomainAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'domain' => $domain,
            'subdomain' => $subdomain,
        ]);

        unset($this->domains);
        $this->dispatch('toast', type: 'success', message: 'Addon domain deleted.', title: 'Success');
    }

    public function createSubdomain(): void
    {
        Server::actions()->createSubdomainAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'subdomain' => $this->subdomain,
        ]);

        $this->reset('subdomain');
        unset($this->domains);
        $this->dispatch('toast', type: 'success', message: 'Subdomain created.', title: 'Success');
    }

    public function deleteSubdomain(string $domain): void
    {
        Server::actions()->deleteSubdomainAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'domain' => $domain,
        ]);

        unset($this->domains);
        $this->dispatch('toast', type: 'success', message: 'Subdomain deleted.', title: 'Success');
    }

    public function parkDomain(): void
    {
        Server::actions()->parkDomainAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'domain' => $this->parked_domain,
        ]);

        $this->reset('parked_domain');
        unset($this->domains);
        $this->dispatch('toast', type: 'success', message: 'Domain parked.', title: 'Success');
    }

    public function unparkDomain(string $domain): void
    {
        Server::actions()->unparkDomainAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
            'domain' => $domain,
        ]);

        unset($this->domains);
        $this->dispatch('toast', type: 'success', message: 'Parked domain removed.', title: 'Success');
    }

    public function requestAutoSsl(): void
    {
        Server::actions()->requestAutoSslAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
        ]);

        unset($this->sslHosts);
        $this->dispatch('toast', type: 'success', message: 'AutoSSL check started.', title: 'Success');
    }

    public function createBackup(): void
    {
        Server::actions()->createBackupAsClient([
            'order_id' => $this->order_id,
            'user_id' => auth()->id(),
        ]);

        unset($this->backups);
        $this->dispatch('toast', type: 'success', message: 'Backup started.', title: 'Success');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function toolList(string $flag, callable $callback): array
    {
        $order = $this->readyOrder($flag);

        if (! $order) {
            return [];
        }

        try {
            return $callback(CpanelAccountManager::for($order->package->serverConnection), $order);
        } catch (Throwable) {
            return [];
        }
    }

    protected function readyOrder(string $flag): ?Order
    {
        $order = $this->order;

        if (! $order || ! Server::usesCpanel($order) || (string) $order->option($flag, '1') !== '1') {
            return null;
        }

        if (! $order->external_id && empty($order->data['username'])) {
            return null;
        }

        return $order;
    }
}

?>

<div wire:poll.60s="refreshPanel">
    @php
        $order = $this->order;
        $summary = $this->summary;
        $canManage = $order && $order->status === 'active';
        $account = $order?->getExternalUser();
        $password = $account?->password;
        $enabled = fn (string $flag) => (string) $order?->option($flag, '1') === '1';
    @endphp

    @if($order)
        <x-theme::card class="mb-4">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ __('server-cpanel::messages.account') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $order->data['domain'] ?? $order->package->name }}
                        @if(!empty($order->data['username']))
                            · {{ $order->data['username'] }}
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($order->status === 'suspended' || ($summary['suspended'] ?? false))
                        <x-theme::badge.warning :text="__('server-cpanel::messages.suspended')" />
                    @elseif($summary && empty($summary['error']))
                        <x-theme::badge.success :text="__('server-cpanel::messages.active')" />
                    @else
                        <x-theme::badge.primary :text="__('server-cpanel::messages.unknown')" />
                    @endif

                    @if($canManage && $enabled('allow_login') && ($order->external_id || !empty($order->data['username'])))
                        <x-theme::button.primary :href="route('cpanel.login', $order)" target="_blank" :text="__('server-cpanel::messages.login')" />
                    @endif
                </div>
            </div>

            @if($order->status === 'suspended')
                <x-theme::alert.warning class="mb-4" :text="__('server-cpanel::messages.suspended')" />
            @endif

            @if(!$order->external_id && empty($order->data['username']))
                <x-theme::alert.primary :text="__('server-cpanel::messages.not_provisioned')" />
            @elseif(($summary['error'] ?? false) === true)
                <x-theme::alert.warning :text="__('server-cpanel::messages.unavailable')" />
            @else
                <x-theme::datagrid.grid :cols="3" :gap="4">
                    <x-theme::datagrid.item>
                        <x-slot:label>{{ __('server-cpanel::messages.domain') }}</x-slot:label>
                        {{ $summary['domain'] ?? ($order->data['domain'] ?? '—') }}
                    </x-theme::datagrid.item>
                    <x-theme::datagrid.item>
                        <x-slot:label>{{ __('server-cpanel::messages.ip') }}</x-slot:label>
                        {{ $summary['ip'] ?? ($order->data['ip'] ?? '—') }}
                    </x-theme::datagrid.item>
                    <x-theme::datagrid.item>
                        <x-slot:label>{{ __('server-cpanel::messages.package') }}</x-slot:label>
                        {{ $summary['package'] ?? ($order->data['package'] ?? '—') }}
                    </x-theme::datagrid.item>
                    <x-theme::datagrid.item>
                        <x-slot:label>{{ __('server-cpanel::messages.username') }}</x-slot:label>
                        {{ $account->username ?? ($order->data['username'] ?? '—') }}
                    </x-theme::datagrid.item>
                    <x-theme::datagrid.item>
                        <x-slot:label>{{ __('server-cpanel::messages.password') }}</x-slot:label>
                        @if($password)
                            <span class="inline-flex items-center gap-2">
                                <span>{{ $showPassword ? $password : str_repeat('•', 10) }}</span>
                                <button type="button" wire:click="$toggle('showPassword')" class="text-xs text-primary-700 hover:underline dark:text-primary-400">
                                    {{ $showPassword ? __('server-cpanel::messages.hide') : __('server-cpanel::messages.show') }}
                                </button>
                            </span>
                        @else
                            —
                        @endif
                    </x-theme::datagrid.item>
                    <x-theme::datagrid.item>
                        <x-slot:label>{{ __('server-cpanel::messages.nameservers') }}</x-slot:label>
                        {{ implode(', ', $summary['nameservers'] ?? ($order->data['nameservers'] ?? [])) ?: '—' }}
                    </x-theme::datagrid.item>
                    <x-theme::datagrid.item>
                        <x-slot:label>{{ __('server-cpanel::messages.disk') }}</x-slot:label>
                        {{ $summary['diskused'] ?? '—' }} / {{ $summary['disklimit'] ?? __('server-cpanel::messages.unlimited') }}
                    </x-theme::datagrid.item>
                    <x-theme::datagrid.item>
                        <x-slot:label>{{ __('server-cpanel::messages.theme') }}</x-slot:label>
                        {{ $summary['theme'] ?? ($order->data['theme'] ?? '—') }}
                    </x-theme::datagrid.item>
                </x-theme::datagrid.grid>
            @endif

            @if($canManage && ($order->external_id || !empty($order->data['username'])))
                <div class="mt-6 flex flex-wrap gap-2 border-b border-gray-200 pb-3 dark:border-gray-700">
                    @foreach([
                        'overview' => __('server-cpanel::messages.overview'),
                        'email' => $enabled('allow_email') ? __('server-cpanel::messages.email') : null,
                        'ftp' => $enabled('allow_ftp') ? __('server-cpanel::messages.ftp') : null,
                        'databases' => $enabled('allow_databases') ? __('server-cpanel::messages.databases') : null,
                        'domains' => $enabled('allow_domains') ? __('server-cpanel::messages.domains') : null,
                        'ssl' => $enabled('allow_ssl') ? __('server-cpanel::messages.ssl') : null,
                        'backups' => $enabled('allow_backups') ? __('server-cpanel::messages.backups') : null,
                    ] as $name => $label)
                        @if($label)
                            <button
                                type="button"
                                wire:click="$set('tab', '{{ $name }}')"
                                class="rounded-lg px-4 py-2 text-sm font-medium {{ $tab === $name ? 'bg-primary-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' }}"
                            >
                                {{ $label }}
                            </button>
                        @endif
                    @endforeach
                </div>
            @endif
        </x-theme::card>

        @if($canManage && ($order->external_id || !empty($order->data['username'])))
            @if($tab === 'overview' && $enabled('allow_password_change'))
                <x-theme::card class="mb-4">
                    <h4 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ __('server-cpanel::messages.change_password') }}</h4>
                    <div class="mb-3 max-w-md">
                        <x-theme::form.label for="cpanel-password" :text="__('server-cpanel::messages.new_password')" />
                        <x-theme::form.input id="cpanel-password" type="password" wire:model="password" />
                        @error('password')
                            <x-theme::form.error :text="$message" />
                        @enderror
                    </div>
                    <x-theme::button.primary type="button" wire:click="changePassword" :text="__('server-cpanel::messages.save_password')" />
                </x-theme::card>
            @endif

            @if($tab === 'email' && $enabled('allow_email'))
                <x-theme::card class="mb-4">
                    <h4 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ __('server-cpanel::messages.create_email') }}</h4>
                    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div>
                            <x-theme::form.label for="email-local" :text="__('server-cpanel::messages.email_local')" />
                            <x-theme::form.input id="email-local" type="text" wire:model="email_local" placeholder="info" />
                            @error('email')
                                <x-theme::form.error :text="$message" />
                            @enderror
                        </div>
                        <div>
                            <x-theme::form.label for="email-password" :text="__('server-cpanel::messages.password')" />
                            <x-theme::form.input id="email-password" type="password" wire:model="email_password" />
                        </div>
                        <div>
                            <x-theme::form.label for="email-quota" :text="__('server-cpanel::messages.quota')" />
                            <x-theme::form.input id="email-quota" type="number" wire:model="email_quota" />
                        </div>
                    </div>
                    <x-theme::button.primary type="button" class="mb-4" wire:click="createEmail" :text="__('server-cpanel::messages.create_email')" />

                    @if(count($this->emails) === 0)
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('server-cpanel::messages.no_email') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($this->emails as $email)
                                        <tr wire:key="email-{{ $email['email'] ?? $loop->index }}">
                                            <td class="py-3 pr-4 font-medium text-gray-900 dark:text-white">{{ $email['email'] ?? $email['login'] ?? '—' }}</td>
                                            <td class="py-3 text-right">
                                                <x-theme::button.danger type="button" wire:click="deleteEmail('{{ $email['email'] ?? $email['login'] }}')" wire:confirm="Delete this email account?" :text="__('server-cpanel::messages.delete')" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-theme::card>
            @endif

            @if($tab === 'ftp' && $enabled('allow_ftp'))
                <x-theme::card class="mb-4">
                    <h4 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ __('server-cpanel::messages.create_ftp') }}</h4>
                    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                        <div>
                            <x-theme::form.label for="ftp-user" :text="__('server-cpanel::messages.ftp_user')" />
                            <x-theme::form.input id="ftp-user" type="text" wire:model="ftp_user" />
                            @error('user')
                                <x-theme::form.error :text="$message" />
                            @enderror
                        </div>
                        <div>
                            <x-theme::form.label for="ftp-password" :text="__('server-cpanel::messages.password')" />
                            <x-theme::form.input id="ftp-password" type="password" wire:model="ftp_password" />
                        </div>
                        <div>
                            <x-theme::form.label for="ftp-quota" :text="__('server-cpanel::messages.quota')" />
                            <x-theme::form.input id="ftp-quota" type="number" wire:model="ftp_quota" />
                        </div>
                        <div>
                            <x-theme::form.label for="ftp-homedir" :text="__('server-cpanel::messages.home_directory')" />
                            <x-theme::form.input id="ftp-homedir" type="text" wire:model="ftp_homedir" />
                        </div>
                    </div>
                    <x-theme::button.primary type="button" class="mb-4" wire:click="createFtp" :text="__('server-cpanel::messages.create_ftp')" />

                    @if(count($this->ftpAccounts) === 0)
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('server-cpanel::messages.no_ftp') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($this->ftpAccounts as $ftp)
                                        <tr wire:key="ftp-{{ $ftp['user'] ?? $loop->index }}">
                                            <td class="py-3 pr-4 font-medium text-gray-900 dark:text-white">{{ $ftp['user'] ?? $ftp['login'] ?? '—' }}</td>
                                            <td class="py-3 text-right">
                                                <x-theme::button.danger type="button" wire:click="deleteFtp('{{ $ftp['user'] ?? $ftp['login'] }}')" wire:confirm="Delete this FTP account?" :text="__('server-cpanel::messages.delete')" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-theme::card>
            @endif

            @if($tab === 'databases' && $enabled('allow_databases'))
                <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <x-theme::card>
                        <h4 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ __('server-cpanel::messages.create_database') }}</h4>
                        <div class="mb-3">
                            <x-theme::form.label for="database-name" :text="__('server-cpanel::messages.database_name')" />
                            <x-theme::form.input id="database-name" type="text" wire:model="database_name" />
                            @error('name')
                                <x-theme::form.error :text="$message" />
                            @enderror
                        </div>
                        <x-theme::button.primary type="button" class="mb-4" wire:click="createDatabase" :text="__('server-cpanel::messages.create_database')" />

                        @forelse($this->databases as $database)
                            <div wire:key="db-{{ $database['database'] ?? $loop->index }}" class="mb-2 flex items-center justify-between text-sm">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $database['database'] ?? $database['name'] ?? '—' }}</span>
                                <x-theme::button.danger type="button" wire:click="deleteDatabase('{{ $database['database'] ?? $database['name'] }}')" wire:confirm="Delete this database?" :text="__('server-cpanel::messages.delete')" />
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('server-cpanel::messages.no_databases') }}</p>
                        @endforelse
                    </x-theme::card>

                    <x-theme::card>
                        <h4 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ __('server-cpanel::messages.create_database_user') }}</h4>
                        <div class="mb-3">
                            <x-theme::form.label for="database-user" :text="__('server-cpanel::messages.database_user')" />
                            <x-theme::form.input id="database-user" type="text" wire:model="database_user" />
                        </div>
                        <div class="mb-3">
                            <x-theme::form.label for="database-user-password" :text="__('server-cpanel::messages.password')" />
                            <x-theme::form.input id="database-user-password" type="password" wire:model="database_user_password" />
                        </div>
                        <x-theme::button.primary type="button" class="mb-4" wire:click="createDatabaseUser" :text="__('server-cpanel::messages.create_database_user')" />

                        @forelse($this->databaseUsers as $user)
                            <div wire:key="dbuser-{{ $user['user'] ?? $loop->index }}" class="mb-2 flex items-center justify-between text-sm">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $user['user'] ?? $user['name'] ?? '—' }}</span>
                                <x-theme::button.danger type="button" wire:click="deleteDatabaseUser('{{ $user['user'] ?? $user['name'] }}')" wire:confirm="Delete this database user?" :text="__('server-cpanel::messages.delete')" />
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('server-cpanel::messages.no_database_users') }}</p>
                        @endforelse
                    </x-theme::card>
                </div>
            @endif

            @if($tab === 'domains' && $enabled('allow_domains'))
                <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <x-theme::card>
                        <h4 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ __('server-cpanel::messages.create_addon') }}</h4>
                        <div class="mb-3">
                            <x-theme::form.label for="addon-domain" :text="__('server-cpanel::messages.domain')" />
                            <x-theme::form.input id="addon-domain" type="text" wire:model="addon_domain" />
                        </div>
                        <div class="mb-3">
                            <x-theme::form.label for="addon-subdomain" :text="__('server-cpanel::messages.subdomain')" />
                            <x-theme::form.input id="addon-subdomain" type="text" wire:model="addon_subdomain" />
                        </div>
                        <x-theme::button.primary type="button" class="mb-4" wire:click="createAddonDomain" :text="__('server-cpanel::messages.create_addon')" />
                        @forelse(($this->domains['addon'] ?? []) as $domain)
                            <div wire:key="addon-{{ $domain['domain'] ?? $loop->index }}" class="mb-2 flex items-center justify-between text-sm">
                                <span>{{ $domain['domain'] ?? $domain['servername'] ?? '—' }}</span>
                                <x-theme::button.danger type="button" wire:click="deleteAddonDomain('{{ $domain['domain'] ?? $domain['servername'] }}', '{{ $domain['subdomain'] ?? '' }}')" :text="__('server-cpanel::messages.delete')" />
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('server-cpanel::messages.no_addon') }}</p>
                        @endforelse
                    </x-theme::card>

                    <x-theme::card>
                        <h4 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ __('server-cpanel::messages.create_subdomain') }}</h4>
                        <div class="mb-3">
                            <x-theme::form.label for="subdomain" :text="__('server-cpanel::messages.subdomain')" />
                            <x-theme::form.input id="subdomain" type="text" wire:model="subdomain" />
                        </div>
                        <x-theme::button.primary type="button" class="mb-4" wire:click="createSubdomain" :text="__('server-cpanel::messages.create_subdomain')" />
                        @forelse(($this->domains['subdomain'] ?? []) as $domain)
                            <div wire:key="sub-{{ $domain['domain'] ?? $loop->index }}" class="mb-2 flex items-center justify-between text-sm">
                                <span>{{ $domain['domain'] ?? $domain['fullsubdomain'] ?? '—' }}</span>
                                <x-theme::button.danger type="button" wire:click="deleteSubdomain('{{ $domain['domain'] ?? $domain['fullsubdomain'] }}')" :text="__('server-cpanel::messages.delete')" />
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('server-cpanel::messages.no_subdomain') }}</p>
                        @endforelse
                    </x-theme::card>

                    <x-theme::card>
                        <h4 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ __('server-cpanel::messages.park_domain') }}</h4>
                        <div class="mb-3">
                            <x-theme::form.label for="parked-domain" :text="__('server-cpanel::messages.domain')" />
                            <x-theme::form.input id="parked-domain" type="text" wire:model="parked_domain" />
                        </div>
                        <x-theme::button.primary type="button" class="mb-4" wire:click="parkDomain" :text="__('server-cpanel::messages.park_domain')" />
                        @forelse(($this->domains['parked'] ?? []) as $domain)
                            <div wire:key="park-{{ $domain['domain'] ?? $loop->index }}" class="mb-2 flex items-center justify-between text-sm">
                                <span>{{ $domain['domain'] ?? '—' }}</span>
                                <x-theme::button.danger type="button" wire:click="unparkDomain('{{ $domain['domain'] }}')" :text="__('server-cpanel::messages.delete')" />
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('server-cpanel::messages.no_parked') }}</p>
                        @endforelse
                    </x-theme::card>
                </div>
            @endif

            @if($tab === 'ssl' && $enabled('allow_ssl'))
                <x-theme::card class="mb-4">
                    <h4 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ __('server-cpanel::messages.ssl_status') }}</h4>
                    <x-theme::button.primary type="button" class="mb-4" wire:click="requestAutoSsl" :text="__('server-cpanel::messages.request_autossl')" />
                    @forelse($this->sslHosts as $host)
                        <div wire:key="ssl-{{ $host['servername'] ?? $loop->index }}" class="mb-2 text-sm text-gray-900 dark:text-white">
                            {{ $host['servername'] ?? $host['domain'] ?? '—' }}
                            @if(!empty($host['certificate']['is_self_signed']))
                                · self-signed
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('server-cpanel::messages.no_ssl') }}</p>
                    @endforelse
                </x-theme::card>
            @endif

            @if($tab === 'backups' && $enabled('allow_backups'))
                <x-theme::card class="mb-4">
                    <h4 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ __('server-cpanel::messages.backups') }}</h4>
                    <x-theme::button.primary type="button" class="mb-4" wire:click="createBackup" :text="__('server-cpanel::messages.create_backup')" />
                    @forelse($this->backups as $backup)
                        <div wire:key="backup-{{ $backup['file'] ?? $loop->index }}" class="mb-2 text-sm text-gray-900 dark:text-white">
                            {{ $backup['file'] ?? $backup['backup'] ?? '—' }}
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('server-cpanel::messages.no_backups') }}</p>
                    @endforelse
                </x-theme::card>
            @endif
        @endif
    @endif
</div>
