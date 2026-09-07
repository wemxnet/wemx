<?php

namespace Extensions\Servers\Cpanel\Support;

use App\Models\Order;
use App\Models\PackagePrice;
use App\Models\ServerConnection;
use Exception;
use Illuminate\Support\Str;

class CpanelAccountManager
{
    public function __construct(
        protected WhmApi $api,
        protected ServerConnection $connection,
    ) {}

    public static function for(ServerConnection $connection): self
    {
        return new self(WhmApi::fromConnection($connection), $connection);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(Order $order): array
    {
        if ($order->external_id) {
            return $this->existingState($order);
        }

        $plan = $this->planFromOrder($order);
        $username = $this->usernameFor($order, $plan);
        $domain = $this->domainFor($order, $plan);
        $password = $this->passwordFor($order);

        $payload = $this->createPayload($order, $plan, $username, $domain, $password);
        $result = $this->api->createAccount($payload);
        $summary = $this->safeAccountSummary($username);

        return $this->provisionedState($order, $plan, $username, $domain, $password, $result, $summary);
    }

    public function suspend(Order $order): void
    {
        $this->api->suspendAccount($this->username($order), 'Suspended by WemX');
    }

    public function unsuspend(Order $order): void
    {
        $this->api->unsuspendAccount($this->username($order));
    }

    public function terminate(Order $order): void
    {
        $this->api->removeAccount($this->username($order));
    }

    /**
     * @return array<string, mixed>
     */
    public function upgrade(Order $order, PackagePrice $newPackagePrice): array
    {
        $username = $this->username($order);
        $package = $newPackagePrice->package;
        $plan = $this->planFromPackage($package);

        if (($plan['package'] ?? '') !== '') {
            $this->api->changePackage($username, (string) $plan['package']);
        }

        if ($this->hasLimit($plan['quota'] ?? null)) {
            $this->api->editQuota($username, (int) $plan['quota']);
        }

        if ($this->hasLimit($plan['bandwidth'] ?? null)) {
            $this->api->limitBandwidth($username, (int) $plan['bandwidth']);
        }

        $data = $order->data ?? [];
        $data['package'] = $plan['package'] ?? ($data['package'] ?? null);
        $data['quota'] = $plan['quota'] ?? ($data['quota'] ?? null);
        $data['bandwidth'] = $plan['bandwidth'] ?? ($data['bandwidth'] ?? null);
        $data['last_error'] = null;

        return $data;
    }

    public function changePassword(Order $order, string $password): void
    {
        $this->api->changePassword($this->username($order), $password);
    }

    /**
     * @return array<string, mixed>
     */
    public function loginUrl(Order $order, string $service = 'cpaneld'): array
    {
        $session = $this->api->createUserSession($this->username($order), $service);
        $url = (string) ($session['url'] ?? '');

        if ($url === '') {
            throw new Exception('WHM did not return a cPanel login URL.');
        }

        return [
            'url' => $url,
            'session' => $session['session'] ?? null,
            'cp_security_token' => $session['cp_security_token'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Order $order): array
    {
        $username = $this->username($order);
        $account = $this->safeAccountSummary($username);
        $usage = $this->safeUsages($username);

        return [
            'username' => $username,
            'domain' => $account['domain'] ?? ($order->data['domain'] ?? null),
            'ip' => $account['ip'] ?? ($order->data['ip'] ?? null),
            'package' => $account['plan'] ?? ($order->data['package'] ?? null),
            'theme' => $account['theme'] ?? ($order->data['theme'] ?? null),
            'email' => $account['email'] ?? null,
            'suspended' => (int) ($account['suspended'] ?? 0) === 1,
            'diskused' => $account['diskused'] ?? null,
            'disklimit' => $account['disklimit'] ?? null,
            'startdate' => $account['startdate'] ?? null,
            'nameservers' => $order->data['nameservers'] ?? [],
            'usages' => $usage,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function emailAccounts(Order $order): array
    {
        return $this->list($this->api->uapi($this->username($order), 'Email', 'list_pops_with_disk'));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createEmailAccount(Order $order, array $input): mixed
    {
        return $this->api->uapi($this->username($order), 'Email', 'add_pop', [
            'email' => $input['email'],
            'password' => $input['password'],
            'quota' => $input['quota'] ?? 0,
            'domain' => $input['domain'] ?? $this->domain($order),
        ]);
    }

    public function deleteEmailAccount(Order $order, string $email, ?string $domain = null): mixed
    {
        [$local, $resolvedDomain] = $this->splitEmail($email, $domain ?? $this->domain($order));

        return $this->api->uapi($this->username($order), 'Email', 'delete_pop', [
            'email' => $local,
            'domain' => $resolvedDomain,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function ftpAccounts(Order $order): array
    {
        return $this->list($this->api->uapi($this->username($order), 'Ftp', 'list_ftp_with_disk'));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createFtpAccount(Order $order, array $input): mixed
    {
        return $this->api->uapi($this->username($order), 'Ftp', 'add_ftp', [
            'user' => $input['user'],
            'pass' => $input['password'],
            'quota' => $input['quota'] ?? 0,
            'homedir' => $input['homedir'] ?? $input['user'],
            'domain' => $input['domain'] ?? $this->domain($order),
        ]);
    }

    public function deleteFtpAccount(Order $order, string $user, bool $destroy = false): mixed
    {
        return $this->api->uapi($this->username($order), 'Ftp', 'delete_ftp', [
            'user' => $user,
            'destroy' => $destroy ? 1 : 0,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function databases(Order $order): array
    {
        return $this->list($this->api->uapi($this->username($order), 'Mysql', 'list_databases'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function databaseUsers(Order $order): array
    {
        return $this->list($this->api->uapi($this->username($order), 'Mysql', 'list_users'));
    }

    public function createDatabase(Order $order, string $name): mixed
    {
        return $this->api->uapi($this->username($order), 'Mysql', 'create_database', [
            'name' => $this->prefixedName($order, $name),
        ]);
    }

    public function deleteDatabase(Order $order, string $name): mixed
    {
        return $this->api->uapi($this->username($order), 'Mysql', 'delete_database', [
            'name' => $name,
        ]);
    }

    public function createDatabaseUser(Order $order, string $name, string $password): mixed
    {
        return $this->api->uapi($this->username($order), 'Mysql', 'create_user', [
            'name' => $this->prefixedName($order, $name),
            'password' => $password,
        ]);
    }

    public function deleteDatabaseUser(Order $order, string $name): mixed
    {
        return $this->api->uapi($this->username($order), 'Mysql', 'delete_user', [
            'name' => $name,
        ]);
    }

    public function grantDatabasePrivileges(Order $order, string $user, string $database): mixed
    {
        return $this->api->uapi($this->username($order), 'Mysql', 'set_privileges_on_database', [
            'user' => $user,
            'database' => $database,
            'privileges' => 'ALL PRIVILEGES',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function domains(Order $order): array
    {
        $username = $this->username($order);

        return [
            'main' => $this->list($this->api->uapi($username, 'DomainInfo', 'list_domains')),
            'addon' => $this->list($this->api->uapi($username, 'AddonDomain', 'listaddondomains')),
            'subdomain' => $this->list($this->api->uapi($username, 'SubDomain', 'listsubdomains')),
            'parked' => $this->list($this->api->uapi($username, 'Park', 'listparkeddomains')),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createAddonDomain(Order $order, array $input): mixed
    {
        $domain = $input['domain'];
        $subdomain = $input['subdomain'] ?? Str::before($domain, '.');

        return $this->api->uapi($this->username($order), 'AddonDomain', 'addaddondomain', [
            'newdomain' => $domain,
            'subdomain' => $subdomain,
            'dir' => $input['dir'] ?? "public_html/{$domain}",
        ]);
    }

    public function deleteAddonDomain(Order $order, string $domain, string $subdomain): mixed
    {
        return $this->api->uapi($this->username($order), 'AddonDomain', 'deladdondomain', [
            'domain' => $domain,
            'subdomain' => $subdomain,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createSubdomain(Order $order, array $input): mixed
    {
        return $this->api->uapi($this->username($order), 'SubDomain', 'addsubdomain', [
            'domain' => $input['subdomain'],
            'rootdomain' => $input['rootdomain'] ?? $this->domain($order),
            'dir' => $input['dir'] ?? 'public_html/'.$input['subdomain'],
        ]);
    }

    public function deleteSubdomain(Order $order, string $domain): mixed
    {
        return $this->api->uapi($this->username($order), 'SubDomain', 'delsubdomain', [
            'domain' => $domain,
        ]);
    }

    public function parkDomain(Order $order, string $domain): mixed
    {
        return $this->api->uapi($this->username($order), 'Park', 'park', [
            'domain' => $domain,
        ]);
    }

    public function unparkDomain(Order $order, string $domain): mixed
    {
        return $this->api->uapi($this->username($order), 'Park', 'unpark', [
            'domain' => $domain,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sslHosts(Order $order): array
    {
        return $this->list($this->api->uapi($this->username($order), 'SSL', 'installed_hosts'));
    }

    public function requestAutoSsl(Order $order): mixed
    {
        return $this->api->uapi($this->username($order), 'SSL', 'start_autossl_check');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function backups(Order $order): array
    {
        return $this->list($this->api->uapi($this->username($order), 'Backup', 'list_backups'));
    }

    public function createHomeBackup(Order $order): mixed
    {
        return $this->api->uapi($this->username($order), 'Backup', 'fullbackup_to_homedir');
    }

    public function username(Order $order): string
    {
        $username = (string) ($order->external_id ?: ($order->data['username'] ?? ''));

        if ($username === '') {
            throw new Exception('This cPanel account has not finished provisioning yet.');
        }

        return $username;
    }

    public function domain(Order $order): string
    {
        return (string) ($order->data['domain'] ?? $order->option('domain', ''));
    }

    /**
     * @return array<string, mixed>
     */
    protected function existingState(Order $order): array
    {
        $data = $order->data ?? [];
        $data['username'] = $order->external_id;
        $data['last_error'] = null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    protected function provisionedState(
        Order $order,
        array $plan,
        string $username,
        string $domain,
        string $password,
        array $result,
        array $summary,
    ): array {
        $nameservers = $this->nameservers($result);

        return [
            'username' => $username,
            'domain' => $summary['domain'] ?? $domain,
            'ip' => $summary['ip'] ?? ($result['ip'] ?? null),
            'package' => $summary['plan'] ?? ($plan['package'] ?? null),
            'theme' => $summary['theme'] ?? ($plan['theme'] ?? null),
            'nameservers' => $nameservers,
            'quota' => $plan['quota'] ?? null,
            'bandwidth' => $plan['bandwidth'] ?? null,
            'last_error' => null,
            'password' => $password,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    protected function createPayload(Order $order, array $plan, string $username, string $domain, string $password): array
    {
        $payload = [
            'username' => $username,
            'domain' => $domain,
            'password' => $password,
            'contactemail' => $order->user?->email,
        ];

        $optional = [
            'plan' => $plan['package'] ?? null,
            'owner' => $plan['owner'] ?? null,
            'quota' => $plan['quota'] ?? null,
            'bwlimit' => $plan['bandwidth'] ?? null,
            'inode' => $plan['inode'] ?? null,
            'maxftp' => $plan['max_ftp'] ?? null,
            'maxpop' => $plan['max_email'] ?? null,
            'maxaddon' => $plan['max_addon'] ?? null,
            'maxsub' => $plan['max_subdomains'] ?? null,
            'maxsql' => $plan['max_sql'] ?? null,
            'ip' => $this->yesNo($plan['dedicated_ip'] ?? null),
            'cgi' => $this->zeroOne($plan['cgi'] ?? null),
            'hasshell' => $this->yesNo($plan['shell'] ?? null),
            'cpmod' => $plan['theme'] ?? null,
            'language' => $plan['locale'] ?? null,
        ];

        foreach ($optional as $key => $value) {
            if ($value !== null && $value !== '') {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function planFromOrder(Order $order): array
    {
        return $this->planFromPackage($order->package, $this->orderOptions($order));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function planFromPackage($package, array $options = []): array
    {
        $keys = [
            'package', 'owner', 'quota', 'bandwidth', 'inode',
            'max_ftp', 'max_email', 'max_addon', 'max_subdomains', 'max_sql',
            'dedicated_ip', 'cgi', 'shell', 'theme', 'locale',
            'domain', 'username',
        ];

        $plan = [];

        foreach ($keys as $key) {
            $plan[$key] = $options[$key] ?? $package?->data($key);
        }

        return $plan;
    }

    /**
     * @return array<string, mixed>
     */
    protected function orderOptions(Order $order): array
    {
        return [
            'domain' => $order->option('domain'),
            'username' => $order->option('username'),
            'package' => $order->option('package'),
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    protected function usernameFor(Order $order, array $plan): string
    {
        $username = (string) ($plan['username'] ?: ($order->data['username'] ?? ''));

        if ($username === '') {
            $domain = $this->domainFor($order, $plan);
            $username = Str::before($domain, '.');
        }

        $username = strtolower(preg_replace('/[^a-z0-9]/', '', $username) ?? '');

        if ($username === '' || ! preg_match('/^[a-z]/', $username)) {
            $username = 'c'.$order->id;
        }

        return substr($username, 0, 16);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    protected function domainFor(Order $order, array $plan): string
    {
        $domain = strtolower(trim((string) ($plan['domain'] ?: ($order->data['domain'] ?? ''))));

        if ($domain === '') {
            throw new Exception('A domain is required to create a cPanel account.');
        }

        return $domain;
    }

    protected function passwordFor(Order $order): string
    {
        $existing = $order->getExternalUser()?->password;

        if (is_string($existing) && $existing !== '' && $existing !== 'unknown') {
            return $existing;
        }

        return Str::password(16);
    }

    /**
     * @return array<string, mixed>
     */
    protected function safeAccountSummary(string $username): array
    {
        try {
            return $this->api->accountSummary($username);
        } catch (Exception) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function safeUsages(string $username): array
    {
        try {
            return $this->list($this->api->uapi($username, 'ResourceUsage', 'get_usages'));
        } catch (Exception) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<int, string>
     */
    protected function nameservers(array $result): array
    {
        $nameservers = $result['nameservers'] ?? [];

        if (is_array($nameservers) && $nameservers !== []) {
            return array_values(array_filter($nameservers));
        }

        return array_values(array_filter([
            $result['nameserver'] ?? $result['nameserver1'] ?? null,
            $result['nameserver2'] ?? null,
            $result['nameserver3'] ?? null,
            $result['nameserver4'] ?? null,
        ]));
    }

    protected function prefixedName(Order $order, string $name): string
    {
        $username = $this->username($order);

        if (str_starts_with($name, $username.'_')) {
            return $name;
        }

        return $username.'_'.$name;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function splitEmail(string $email, string $fallbackDomain): array
    {
        if (str_contains($email, '@')) {
            return [Str::before($email, '@'), Str::after($email, '@')];
        }

        return [$email, $fallbackDomain];
    }

    protected function list(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_is_list($value) ? $value : [$value];
    }

    protected function hasLimit(mixed $value): bool
    {
        return $value !== null && $value !== '' && strtolower((string) $value) !== 'unlimited';
    }

    protected function yesNo(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return in_array((string) $value, ['1', 'y', 'yes'], true) ? 'y' : 'n';
    }

    protected function zeroOne(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return in_array((string) $value, ['1', 'y', 'yes'], true) ? 1 : 0;
    }
}
