<?php

namespace Extensions\Servers\Cpanel;

use App\Extensions\Foundation\ServerExtension;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\ServerConnection;
use Exception;
use Extensions\Servers\Cpanel\Actions\CpanelAccountActions;
use Extensions\Servers\Cpanel\Support\CpanelAccountManager;
use Extensions\Servers\Cpanel\Support\WhmApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Server extends ServerExtension
{
    protected string $id = 'server-cpanel';

    protected string $name = 'cPanel & WHM';

    protected string $description = 'Resell cPanel hosting accounts through WHM API 1 with client email, FTP, database, domain, and SSL tools.';

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
                'view' => 'server-cpanel::client_area.default.orders.widgets.account-panel',
            ],
            [
                'element' => 'admin-order-sidebar-view',
                'view' => 'server-cpanel::admin_area.default.orders.widgets.account-sidebar',
            ],
        ];
    }

    public function setConfig(): array
    {
        $doesNotEndWithSlash = function ($attribute, $value, $fail) {
            if (is_string($value) && preg_match('/\/$/', $value)) {
                $fail('Hostname must not end with a slash. Use https://whm.example.com');
            }
        };

        $mustBeHttps = function ($attribute, $value, $fail) {
            if (is_string($value) && $value !== '' && ! str_starts_with($value, 'https://')) {
                $fail('Hostname must start with https://');
            }
        };

        return [
            [
                'key' => 'hostname',
                'name' => 'Hostname',
                'description' => 'WHM host without a trailing slash, for example https://whm.example.com',
                'type' => 'text',
                'default_value' => 'https://whm.example.com',
                'rules' => ['required', 'string', $mustBeHttps, $doesNotEndWithSlash],
            ],
            [
                'key' => 'port',
                'name' => 'Port',
                'description' => 'WHM SSL port. Default is 2087.',
                'type' => 'number',
                'default_value' => 2087,
                'rules' => ['required', 'numeric', 'min:1', 'max:65535'],
            ],
            [
                'key' => 'username',
                'name' => 'WHM username',
                'description' => 'Root or reseller username that owns the API token.',
                'type' => 'text',
                'default_value' => 'root',
                'rules' => ['required', 'string'],
            ],
            [
                'key' => 'api_token',
                'name' => 'API token',
                'description' => 'Preferred. Create a token in WHM → Development → Manage API Tokens.',
                'type' => 'password',
                'rules' => ['nullable', 'string'],
            ],
            [
                'key' => 'access_hash',
                'name' => 'Access hash',
                'description' => 'Legacy remote access key. Use an API token when possible.',
                'type' => 'textarea',
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
                'default_value' => '1',
                'rules' => ['required', 'in:0,1'],
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
        $packageOptions = $this->cachedPackageOptions($connection);
        $themeOptions = $this->cachedThemeOptions($connection);

        $packageField = $packageOptions === []
            ? [
                'key' => 'package',
                'name' => 'WHM package',
                'col' => 'col-4',
                'description' => 'Hosting plan name from WHM → Packages. Connection is offline, so enter the name manually.',
                'type' => 'text',
                'rules' => ['required', 'string'],
                'is_configurable' => false,
            ]
            : [
                'key' => 'package',
                'name' => 'WHM package',
                'col' => 'col-4',
                'description' => 'Hosting plan created in WHM. Customers inherit these limits unless you override them below.',
                'type' => 'select',
                'options' => $packageOptions,
                'default_value' => array_key_first($packageOptions),
                'rules' => ['required', 'string'],
                'is_configurable' => false,
            ];

        $themeField = $themeOptions === []
            ? [
                'key' => 'theme',
                'name' => 'cPanel theme',
                'col' => 'col-4',
                'description' => 'Interface theme assigned to new accounts, usually jupiter.',
                'type' => 'text',
                'default_value' => 'jupiter',
                'rules' => ['nullable', 'string'],
                'is_configurable' => false,
            ]
            : [
                'key' => 'theme',
                'name' => 'cPanel theme',
                'col' => 'col-4',
                'description' => 'Interface theme assigned to new accounts.',
                'type' => 'select',
                'options' => $themeOptions,
                'default_value' => array_key_exists('jupiter', $themeOptions) ? 'jupiter' : array_key_first($themeOptions),
                'rules' => ['nullable', 'string'],
                'is_configurable' => false,
            ];

        return [
            $packageField,
            [
                'key' => 'owner',
                'name' => 'Reseller / owner',
                'col' => 'col-4',
                'description' => 'Optional WHM reseller that should own the account. Leave empty to use the connection user.',
                'type' => 'text',
                'rules' => ['nullable', 'string'],
                'is_configurable' => false,
            ],
            [
                'key' => 'quota',
                'name' => 'Disk quota (MB)',
                'col' => 'col-4',
                'description' => 'Override the WHM package disk quota. Use 0 for unlimited.',
                'type' => 'number',
                'default_value' => 1024,
                'min' => 0,
                'rules' => ['nullable', 'numeric', 'min:0'],
                'is_configurable' => true,
            ],
            [
                'key' => 'bandwidth',
                'name' => 'Bandwidth (MB)',
                'col' => 'col-4',
                'description' => 'Override the WHM package monthly bandwidth. Use 0 for unlimited.',
                'type' => 'number',
                'default_value' => 10240,
                'min' => 0,
                'rules' => ['nullable', 'numeric', 'min:0'],
                'is_configurable' => true,
            ],
            [
                'key' => 'inode',
                'name' => 'Inode limit',
                'col' => 'col-4',
                'description' => 'Optional maximum inodes. Leave empty to inherit the WHM package.',
                'type' => 'number',
                'min' => 0,
                'rules' => ['nullable', 'numeric', 'min:0'],
                'is_configurable' => false,
            ],
            [
                'key' => 'max_ftp',
                'name' => 'Max FTP accounts',
                'col' => 'col-4',
                'description' => 'Optional override. Leave empty to inherit the WHM package.',
                'type' => 'number',
                'min' => 0,
                'rules' => ['nullable', 'numeric', 'min:0'],
                'is_configurable' => false,
            ],
            [
                'key' => 'max_email',
                'name' => 'Max email accounts',
                'col' => 'col-4',
                'description' => 'Optional override. Leave empty to inherit the WHM package.',
                'type' => 'number',
                'min' => 0,
                'rules' => ['nullable', 'numeric', 'min:0'],
                'is_configurable' => false,
            ],
            [
                'key' => 'max_addon',
                'name' => 'Max addon domains',
                'col' => 'col-4',
                'description' => 'Optional override. Leave empty to inherit the WHM package.',
                'type' => 'number',
                'min' => 0,
                'rules' => ['nullable', 'numeric', 'min:0'],
                'is_configurable' => false,
            ],
            [
                'key' => 'max_subdomains',
                'name' => 'Max subdomains',
                'col' => 'col-4',
                'description' => 'Optional override. Leave empty to inherit the WHM package.',
                'type' => 'number',
                'min' => 0,
                'rules' => ['nullable', 'numeric', 'min:0'],
                'is_configurable' => false,
            ],
            [
                'key' => 'max_sql',
                'name' => 'Max SQL databases',
                'col' => 'col-4',
                'description' => 'Optional override. Leave empty to inherit the WHM package.',
                'type' => 'number',
                'min' => 0,
                'rules' => ['nullable', 'numeric', 'min:0'],
                'is_configurable' => false,
            ],
            [
                'key' => 'dedicated_ip',
                'name' => 'Dedicated IP',
                'col' => 'col-4',
                'description' => 'Assign a dedicated IPv4 address when the WHM server has free IPs.',
                'type' => 'select',
                'options' => [
                    'n' => 'No',
                    'y' => 'Yes',
                ],
                'default_value' => 'n',
                'rules' => ['required', 'in:y,n'],
                'is_configurable' => false,
            ],
            [
                'key' => 'cgi',
                'name' => 'CGI',
                'col' => 'col-4',
                'description' => 'Enable CGI for new accounts.',
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
                'key' => 'shell',
                'name' => 'SSH shell',
                'col' => 'col-4',
                'description' => 'Grant SSH/jailshell access. Most shared plans should keep this disabled.',
                'type' => 'select',
                'options' => [
                    'n' => 'Disabled',
                    'y' => 'Enabled',
                ],
                'default_value' => 'n',
                'rules' => ['required', 'in:y,n'],
                'is_configurable' => false,
            ],
            $themeField,
            [
                'key' => 'locale',
                'name' => 'Locale',
                'col' => 'col-4',
                'description' => 'cPanel interface language.',
                'type' => 'select',
                'options' => [
                    'en' => 'English',
                    'de' => 'German',
                    'es' => 'Spanish',
                    'fr' => 'French',
                    'nl' => 'Dutch',
                    'pt' => 'Portuguese',
                    'pt_br' => 'Portuguese (Brazil)',
                    'it' => 'Italian',
                ],
                'default_value' => 'en',
                'rules' => ['required', 'string'],
                'is_configurable' => true,
            ],
            [
                'key' => 'allow_login',
                'name' => 'Allow cPanel login',
                'col' => 'col-4',
                'description' => 'Let customers open a one-click cPanel session.',
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
                'key' => 'allow_password_change',
                'name' => 'Allow password change',
                'col' => 'col-4',
                'description' => 'Let customers change the cPanel account password.',
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
                'key' => 'allow_email',
                'name' => 'Allow email accounts',
                'col' => 'col-4',
                'description' => 'Show email account tools in the client panel.',
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
                'key' => 'allow_ftp',
                'name' => 'Allow FTP accounts',
                'col' => 'col-4',
                'description' => 'Show FTP account tools in the client panel.',
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
                'key' => 'allow_databases',
                'name' => 'Allow databases',
                'col' => 'col-4',
                'description' => 'Show MySQL database and user tools in the client panel.',
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
                'key' => 'allow_domains',
                'name' => 'Allow domain tools',
                'col' => 'col-4',
                'description' => 'Show addon, subdomain, and parked domain tools.',
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
                'key' => 'allow_ssl',
                'name' => 'Allow SSL tools',
                'col' => 'col-4',
                'description' => 'Show installed certificates and AutoSSL requests.',
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
                'key' => 'allow_backups',
                'name' => 'Allow backups',
                'col' => 'col-4',
                'description' => 'Let customers list backups and request a full home-directory backup.',
                'type' => 'select',
                'options' => [
                    '1' => 'Enabled',
                    '0' => 'Disabled',
                ],
                'default_value' => '1',
                'rules' => ['required', 'in:0,1'],
                'is_configurable' => false,
            ],
        ];
    }

    public function setCheckoutConfig(Package $package): array
    {
        return [
            [
                'key' => 'domain',
                'name' => 'Domain',
                'description' => 'The primary domain for this hosting account, for example example.com',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:191', 'regex:/^(?=.{1,253}$)(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))+$/'],
                'is_configurable' => true,
            ],
            [
                'key' => 'username',
                'name' => 'cPanel username',
                'description' => 'Optional. 1-16 lowercase letters and numbers, starting with a letter. Leave empty to generate from the domain.',
                'type' => 'text',
                'rules' => ['nullable', 'string', 'min:1', 'max:16', 'regex:/^[a-z][a-z0-9]{0,15}$/'],
                'is_configurable' => true,
            ],
        ];
    }

    public static function testConnection(array $credentials): string
    {
        $version = WhmApi::make($credentials)->version();
        $release = $version['version'] ?? $version['release'] ?? 'unknown';

        return "Connected to cPanel & WHM {$release}.";
    }

    public function create(Order $order, ServerConnection $connection): void
    {
        try {
            $data = CpanelAccountManager::for($connection)->create($order);
            self::actions()->storeProvisionedState($order, $data);
        } catch (Exception $exception) {
            self::actions()->rememberError($order, $exception->getMessage());
            throw $exception;
        }

        $order->refresh();

        $order->user->email([
            'identifier' => 'server.cpanel.created',
            'mailable_type' => Order::class,
            'mailable_id' => $order->id,
            'variables' => [
                'domain' => $order->data['domain'] ?? '',
                'username' => $order->data['username'] ?? '',
                'password' => $order->getExternalUser()?->password,
                'ip' => $order->data['ip'] ?? '',
                'nameservers' => implode(', ', $order->data['nameservers'] ?? []),
            ],
            'button' => [
                'url' => route('orders.view', $order->id),
            ],
        ]);
    }

    public function suspend(Order $order, ServerConnection $connection): void
    {
        try {
            CpanelAccountManager::for($connection)->suspend($order);
        } catch (Exception $exception) {
            self::actions()->rememberError($order, $exception->getMessage());
            throw $exception;
        }
    }

    public function unsuspend(Order $order, ServerConnection $connection): void
    {
        try {
            CpanelAccountManager::for($connection)->unsuspend($order);
        } catch (Exception $exception) {
            self::actions()->rememberError($order, $exception->getMessage());
            throw $exception;
        }
    }

    public function terminate(Order $order, ServerConnection $connection): void
    {
        try {
            CpanelAccountManager::for($connection)->terminate($order);
        } catch (Exception $exception) {
            self::actions()->rememberError($order, $exception->getMessage());
            throw $exception;
        }
    }

    public function upgradeOrDowngrade(Order $order, PackagePrice $oldPackagePrice, PackagePrice $newPackagePrice, ServerConnection $connection): void
    {
        try {
            $data = CpanelAccountManager::for($connection)->upgrade($order, $newPackagePrice);
            $order->update(['data' => $data]);
        } catch (Exception $exception) {
            self::actions()->rememberError($order, $exception->getMessage());
            throw $exception;
        }
    }

    public function changePassword(Order $order, string $newPassword): void
    {
        CpanelAccountManager::for($order->package->serverConnection)->changePassword($order, $newPassword);
        $order->updateExternalPassword($newPassword);
    }

    public static function actions(): CpanelAccountActions
    {
        return new CpanelAccountActions;
    }

    public static function usesCpanel(?Order $order): bool
    {
        return $order?->package?->serverConnection?->extension_identifier === 'server-cpanel';
    }

    /**
     * @return array<string, string>
     */
    protected function cachedPackageOptions(ServerConnection $connection): array
    {
        $connectionId = $connection->id ?? 'new';

        try {
            return Cache::remember("cpanel:packages:{$connectionId}", now()->addHour(), function () use ($connection) {
                return collect(WhmApi::fromConnection($connection)->listPackages())
                    ->mapWithKeys(function ($package) {
                        $name = $package['name'] ?? null;

                        if (! $name) {
                            return [];
                        }

                        $quota = $package['QUOTA'] ?? 'unlimited';
                        $label = $quota === 'unlimited' ? $name : "{$name} ({$quota} MB)";

                        return [$name => $label];
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
    protected function cachedThemeOptions(ServerConnection $connection): array
    {
        $connectionId = $connection->id ?? 'new';

        try {
            return Cache::remember("cpanel:styles:{$connectionId}", now()->addHour(), function () use ($connection) {
                return collect(WhmApi::fromConnection($connection)->listStyles())
                    ->mapWithKeys(function ($style) {
                        $name = $style['name'] ?? $style['style'] ?? null;

                        return $name ? [$name => Str::headline((string) $name)] : [];
                    })
                    ->all();
            });
        } catch (Exception) {
            return [];
        }
    }
}
