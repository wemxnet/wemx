<?php

namespace Extensions\Servers\Pterodactyl;

use App\Extensions\Foundation\ServerExtension;
use App\Models\Order;
use App\Models\Package;
use App\Models\ServerConnection;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Server extends ServerExtension
{
    /**
     * Define the extension identifier. This identifier should be unique.
     * For example, if the extension name is "Example Module", the extension identifier should be "module-example".
     */
    protected string $id = 'server-pterodactyl';

    /**
     * Define the extension display name
     */
    protected string $name = 'Pterodactyl Server';

    /**
     * Define the extension description.
     */
    protected string $description = 'Pterodactyl server extension';

    /**
     * Define the extension type. For example, if the extension is a module, the extension type should be "Module".
     */
    protected string $type = 'Server';

    /**
     * Define the extension version.
     */
    protected string $version = '1.0.0';

    /**
     * Define the WemX versions that the extension is compatible with.
     * Use * to define that the extension is compatible with all versions.
     */
    protected array $wemxVersions = ['1.0.0'];

    /**
     * Define the authors of the extension.
     */
    protected array $authors = [
        [
            'name' => 'GIGABAIT',
            'email' => 'xgigabaitx@gmail.com',
        ],
    ];

    /**
     * Relative path to the extension config file.
     */
    protected string $config = 'Config/pterodactyl.php';

    /**
     * Relative path to the language files.
     */
    protected string $translations = 'Lang';

    /**
     * List of providers to be registered.
     */
    public function providers(): array
    {
        return [];
    }

    public function elements(): array
    {
        return [];
    }

    public function setConfig(): array
    {
        // Check if the URL ends with a slash
        $doesNotEndWithSlash = function ($attribute, $value, $fail) {
            if (preg_match('/\/$/', $value)) {
                return $fail('Hostname URL must not end with a slash "/". It should be like https://panel.example.com');
            }
        };

        return [
            [
                'key' => 'hostname',
                'name' => 'Hostname',
                'description' => 'Hostname of your Pterodactyl panel i.e https://panel.example.com',
                'type' => 'url',
                'default_value' => 'https://panel.example.com',
                'rules' => ['required', 'active_url', $doesNotEndWithSlash], // laravel validation rules
            ],
            [
                'key' => 'api_key',
                'name' => 'API Key',
                'description' => 'API Key of your Pterodactyl panel',
                'type' => 'password',
                'rules' => ['required', 'starts_with:ptlc_,ptla_'], // laravel validation rules
            ],
            [
                'key' => 'debug_mode',
                'name' => 'Debug Mode',
                'description' => 'When enabled, API errors will be dumped on the screen. Useful for debugging. Do not enable on production.',
                'type' => 'select',
                'options' => [
                    '0' => 'Disabled',
                    '1' => 'Enabled',
                ],
                'default_value' => '0',
                'rules' => ['required', 'in:0,1'], // laravel validation rules
            ],
        ];
    }

    public function setPackageConfig(Package $package, ServerConnection $connection): array
    {
        $config = [
            [
                'key' => 'location_id',
                'name' => 'Location ID',
                'col' => 'col-12',
                'description' => 'The location on which the server should be deployed. Make this option configurable to allow users to select the location.',
                'type' => 'text',
                'rules' => ['required'],
                'is_configurable' => true,
            ],
            [
                'key' => 'nest_id',
                'name' => 'Nest ID',
                'col' => 'col-12',
                'description' => 'Nest ID of the server you want to use for this package. You can find the nest ID by going to the nest page and looking at the URL. It will be the number at the end of the URL.',
                'type' => 'text',
                'rules' => ['required', 'numeric'],
                'is_configurable' => false,
            ],
            [
                'key' => 'egg_id',
                'name' => 'Egg ID',
                'col' => 'col-12',
                'description' => 'Egg ID of the server you want to use for this package. You can find the egg ID by going to the egg page and looking at the URL. It will be the number at the end of the URL.',
                'type' => 'text',
                'rules' => ['required', 'numeric'],
                'is_configurable' => false,
            ],
        ];

        try {
            // if egg id is not set return the default config
            if (! $package->data('egg_id')) {
                return $config;
            }

            $nestId = (int) $package->data('nest_id', 1);
            $eggId = (int) $package->data('egg_id', 4);

            // Unique per connection + nest + egg
            $connectionId = $connection->id ?? ($connection->getKey() ?? 'default');
            $cacheKey = "pterodactyl:egg:{$connectionId}:nest:{$nestId}:egg:{$eggId}";

            // Cache the *attributes* for 1 hour
            $eggAttributes = Cache::remember(
                $cacheKey,
                now()->addHour(),
                function () use ($connection, $nestId, $eggId) {
                    $egg = Server::makeRequest(
                        $connection->config,
                        "/api/application/nests/{$nestId}/eggs/{$eggId}",
                        'get',
                        ['include' => 'variables']
                    );

                    if (! isset($egg['attributes'])) {
                        throw new \RuntimeException('Invalid egg response from panel.');
                    }

                    return $egg['attributes'];
                }
            );

            $config = array_merge($config, [
                [
                    'col' => 'col-4',
                    'key' => 'database_limit',
                    'name' => 'Database Limit',
                    'description' => 'The total number of databases a user is allowed to create for this server on Pterodactyl Panel.',
                    'type' => 'number',
                    'min' => 0,
                    'rules' => ['required', 'numeric', 'min:0', 'max:50'],
                    'is_configurable' => true,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'allocation_limit',
                    'name' => 'Allocation Limit',
                    'description' => 'The total number of allocations a user is allowed to create for this server on Pterodactyl Panel.',
                    'type' => 'number',
                    'min' => 0,
                    'rules' => ['required', 'numeric', 'min:0', 'max:50'],
                    'is_configurable' => true,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'backup_limit',
                    'name' => 'Backup Limit',
                    'description' => 'The total number of backups a user is allowed to create for this server on Pterodactyl Panel.',
                    'type' => 'number',
                    'min' => 0,
                    'rules' => ['required', 'numeric', 'min:0', 'max:100'],
                    'is_configurable' => true,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'cpu_limit',
                    'name' => 'CPU Limit in %',
                    'description' => 'If you do not want to limit CPU usage, set the value to 0. To use a single thread set it to 100%, for 4 threads set to 400% etc',
                    'type' => 'number',
                    'default_value' => 100,
                    'min' => 0,
                    'rules' => ['required', 'numeric', 'min:0', 'max:10000'],
                    'is_configurable' => true,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'memory_limit',
                    'name' => 'Memory Limit in GB',
                    'description' => 'The maximum amount of memory allowed for this container. Setting this to 0 will allow unlimited memory in a container.',
                    'type' => 'number',
                    'min' => 0,
                    'rules' => ['required', 'numeric', 'min:0', 'max:64'],
                    'is_configurable' => true,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'disk_limit',
                    'name' => 'Disk Limit in GB',
                    'description' => 'The maximum amount of memory allowed for this container. Setting this to 0 will allow unlimited memory in a container.',
                    'type' => 'number',
                    'min' => 0,
                    'rules' => ['required', 'numeric', 'min:0', 'max:1024'],
                    'is_configurable' => true,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'cpu_pinning',
                    'name' => 'CPU Pinning (optional)',
                    'description' => 'Advanced: Enter the specific CPU threads that this process can run on, or leave blank to allow all threads. This can be a single number, or a comma separated list. Example: 0, 0-1,3, or 0,1,3,4.',
                    'type' => 'text',
                    'rules' => ['nullable'],
                    'is_configurable' => false,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'swap_limit',
                    'name' => 'Swap Limit in GB',
                    'description' => 'The maximum amount of swap allowed for this container. Setting this to 0 will disable swap. Setting this to -1 will allow unlimited swap.',
                    'type' => 'number',
                    'default_value' => 0,
                    'rules' => ['required', 'numeric', 'min:-1', 'max:128'],
                    'is_configurable' => false,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'block_io_weight',
                    'name' => 'Block IO Weight',
                    'description' => 'The relative weight of IO for this container. This accepts a value between 10 and 1000. The default value is 500.',
                    'type' => 'number',
                    'default_value' => 500,
                    'rules' => ['required', 'numeric', 'min:10', 'max:1000'],
                    'is_configurable' => false,
                ],
            ]);

            $config[] = [
                'col' => 'col-12',
                'key' => 'docker_image',
                'name' => 'Docker Image',
                'description' => 'Docker image to use for this server',
                'type' => 'text',
                'default_value' => data_get($eggAttributes, 'docker_image'),
                'rules' => ['required'],
            ];

            $config[] = [
                'col' => 'col-12',
                'key' => 'startup',
                'name' => 'Startup Command',
                'description' => 'Startup command for this server',
                'type' => 'textarea',
                'default_value' => data_get($eggAttributes, 'startup'),
                'rules' => ['required'],
                'is_configurable' => false,
            ];

            foreach (data_get($eggAttributes, 'relationships.variables.data', []) as $variable) {
                $variable = $variable['attributes'];

                // check if rules is a string, if so convert it to array
                if (is_string($variable['rules'])) {
                    $variable['rules'] = explode('|', $variable['rules']);
                }

                $config[] = [
                    'col' => 'col-4',
                    'key' => "environment.{$variable['env_variable']}",
                    'name' => $variable['name'],
                    'description' => $variable['description'],
                    'type' => 'text',
                    'default_value' => $variable['default_value'] ?? '',
                    'rules' => $variable['rules'],
                    'is_configurable' => true,
                ];
            }
        } catch (\Throwable $e) {
            // if we reach here, the egg id is invalid or the egg does not exist
            // return the default config
            return $config;
        }

        return $config;
    }

    /**
     * This function is called right before the user makes the payment
     * We can use it to check if there are allocations available
     *
     * @throw Exception
     */
    public static function eventAddToCart(Package $package, $configOptions = [])
    {
        // get location id from the package data
        $locationId = $configOptions['location_id'] ?? $package->data('location_id', null);

        if (! $locationId) {
            throw new Exception('Location ID has not been configured for this package');
        }

        Server::findViableNode(
            connection: $package->serverConnection,
            allowedLocations: [$locationId],
            diskLimit: $configOptions['disk_limit'] ?? $package->data('disk_limit', 0),
            memoryLimit: $configOptions['memory_limit'] ?? $package->data('memory_limit', 0),
            cpuLimit: $configOptions['cpu_limit'] ?? $package->data('cpu_limit', 0),
        );
    }

    /**
     * Test API connection
     */
    public static function testConnection(array $credentials)
    {
        Server::makeRequest($credentials, '/api/application/users');

        // throw new \Exception('This method is not implemented yet. Please implement the testConnection method in the Server class.');
        return true;
    }

    /**
     * Make API request to Pterodactyl API
     */
    public static function makeRequest(array $credentials, $endpoint, $method = 'get', $data = [])
    {
        $method = strtolower($method);

        $apiKey = $credentials['api_key'] ?? '';
        $hostname = $credentials['hostname'] ?? '';

        if (! in_array($method, ['get', 'post', 'put', 'delete', 'patch'])) {
            throw new Exception('Invalid method');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Accept' => 'Application/vnd.Pterodactyl.v1+json',
            'Content-Type' => 'application/json',
        ])->$method($hostname.$endpoint, $data);

        if ($response->failed()) {
            throw new Exception("Failed to connect to Pterodactyl API at endpoint: $endpoint with status code: {$response->status()} and response: {$response->body()}");
        }

        return $response;
    }

    /**
     * Changes the password of the Pterodactyl user associated with the order.
     */
    public function changePassword(Order $order, string $newPassword)
    {
        $pterodactylUser = $order->getExternalUser()->data;

        $response = Server::makeRequest($order->package->serverConnection->config, "/api/application/users/{$pterodactylUser['id']}", 'patch', [
            'email' => $pterodactylUser['email'],
            'username' => $pterodactylUser['username'],
            'first_name' => $pterodactylUser['first_name'],
            'last_name' => $pterodactylUser['last_name'],
            'password' => $newPassword,
        ]);

        $order->updateExternalPassword($newPassword);
    }

    /**
     * This function is responsible for creating an instance of the
     * service. This can be anything such as a server, vps or any other instance.
     *
     * @return void
     */
    public function create(Order $order, ServerConnection $connection)
    {
        // define variables
        $pteroUserId = $this->getOrCreatePteroUser($order, $connection);
        $package = $order->package;

        $locationId = $order->option('location_id');

        // specify limits and convert them to MB
        $diskLimit = $order->option('disk_limit', 0) != 0 ? $order->option('disk_limit', 0) * 1024 : 0;
        $memoryLimit = $order->option('memory_limit', 0) != 0 ? $order->option('memory_limit', 0) * 1024 : 0;
        $swapLimit = $order->option('swap_limit', 0) != 0 ? $order->option('swap_limit', 0) * 1024 : 0;
        $cpuLimit = $order->option('cpu_limit', 0);

        $node = Server::findViableNode(
            $connection,
            allowedLocations: [$locationId],
            diskLimit: $diskLimit,
            memoryLimit: $memoryLimit,
            cpuLimit: $cpuLimit
        );

        // merge environment variables from package and order
        $environment = array_merge(
            $order->package->data('environment', []),
            $order->option('environment', [])
        );

        // prepare the server data
        $serverData = [
            'external_id' => "wemx_{$order->id}",
            'name' => $package->name,
            'user' => $pteroUserId,
            'egg' => $package->data('egg_id'),
            'startup' => $package->data('startup'),
            'docker_image' => $package->data('docker_image'),
            'environment' => $environment,
            'limits' => [
                'memory' => $memoryLimit,
                'swap' => $swapLimit,
                'disk' => $diskLimit,
                'io' => $order->option('block_io_weight', 500),
                'cpu' => $cpuLimit,
            ],
            'feature_limits' => [
                'databases' => $order->option('database_limit', 0),
                'allocations' => $order->option('allocation_limit', 0),
                'backups' => $order->option('backup_limit', 0),
            ],
            'allocation' => [
                'default' => $node['allocation_id'],
            ],
            'start_on_completion' => true,
            'skip_scripts' => false,
            'oom_disabled' => false,
            'swap_disabled' => false,
        ];

        // Create the server on Pterodactyl panel
        $createServerResponse = Server::makeRequest($connection->config, '/api/application/servers', 'post', $serverData);

        // check if the server was created successfully
        if (! isset($createServerResponse['attributes'])) {
            throw new Exception('Failed to create server on Pterodactyl panel');
        }

        $server = $createServerResponse['attributes'];

        // store the server data locally
        $order->update([
            'external_id' => $server['id'],
            'data' => $server,
        ]);
    }

    /**
     * Create the user on Pterodactyl panel and store the data locally
     * If the user already exists, return the user id on Pterodactyl panel
     */
    private function getOrCreatePteroUser(Order $order, ServerConnection $connection): int
    {
        $user = $order->user;

        try {
            // Attempt to find the user on Pterodactyl with the same email
            $userEmailResponse = Server::makeRequest($connection->config, '/api/application/users', 'get', [
                'filter[email]' => $user->email,
            ]);

            // if api returns a user, store the user data locally and return the user id
            if (isset($userEmailResponse['data'][0])) {
                $this->storePteroUserLocally(
                    $order,
                    $userEmailResponse['data'][0]['attributes']
                );

                return $userEmailResponse['data'][0]['attributes']['id'];
            }
        } catch (Exception $e) {
            dd($e->getMessage());
        }

        // attempt to create the user on Pterodactyl
        $randomPassword = Str::random(16);
        $createUserResponse = Server::makeRequest($connection->config, '/api/application/users', 'post', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'username' => $user->username.$user->id, // username must be unique
            'password' => $randomPassword,
        ]);

        // email the user their Pterodactyl panel credentials
        $this->emailPteroCredentials(
            $order,
            $user->email,
            $randomPassword
        );

        // store the user data locally
        $pteroUserData = array_merge($createUserResponse['attributes'], ['password' => $randomPassword]);
        $this->storePteroUserLocally($order, $pteroUserData);

        return $createUserResponse['attributes']['id'];
    }

    /**
     * Store the Pterodactyl user data locally for future reference
     */
    private function storePteroUserLocally(Order $order, array $pteroUserData): void
    {
        $order->createExternalUser([
            'external_id' => $pteroUserData['id'],
            'username' => $pteroUserData['username'],
            'password' => $pteroUserData['password'] ?? 'unknown',
            'data' => $pteroUserData,
        ]);
    }

    /**
     * Email the user their Pterodactyl panel credentials
     */
    private function emailPteroCredentials(Order $order, string $email, string $password): void
    {
        $order->user->email([
            'identifier' => 'server.pterodactyl.account_created',
            'mailable_type' => Order::class,
            'mailable_id' => $order->id,
            'variables' => [
                'panel_email' => $email,
                'panel_password' => $password,
            ],
            'button' => [
                'url' => 'https://panel.example.com',
            ],
        ]);
    }

    /**
     * Find a viable node based on the order requirements
     *
     * Returns the node id and allocation id
     */
    private static function findViableNode(ServerConnection $connection, array $allowedLocations = [], string|int $diskLimit = 0, string|int $memoryLimit = 0, string|int $cpuLimit = 0): array
    {
        $findDeployableNodes = Server::makeRequest($connection->config, '/api/application/nodes/deployable', 'get', [
            'disk' => $diskLimit,
            'memory' => $memoryLimit,
            'cpu' => $cpuLimit,
            'include' => 'allocations',
        ]);

        if (! isset($findDeployableNodes['data']) or empty($findDeployableNodes['data'])) {
            throw new Exception('Could not find node satisfying the requirements');
        }

        $nodes = $findDeployableNodes['data'];

        foreach ($nodes as $node) {
            $node = $node['attributes'];

            // if node is not in allowed nodes, skip
            if (! empty($allowedLocations) and ! in_array($node['id'], $allowedLocations)) {
                continue;
            }

            // now that we have determined the node, lets find an allocation
            $allocations = $node['relationships']['allocations']['data'];

            // lets go over each allocation and ensure its not in use
            foreach ($allocations as $allocation) {
                $allocation = $allocation['attributes'];

                // check if the allocation is in use
                if ($allocation['assigned']) {
                    continue;
                }

                // allocation is not in use, return the node id and allocation id
                return [
                    'node_id' => $node['id'],
                    'allocation_id' => $allocation['id'],
                ];
            }

            // if we reach here, no allocation was found
            // in the future, add logic to create a new allocation
            // on one of the available nodes

            // for now, throw an exception
            throw new Exception('Could not find a free allocation on the node, please contact support');
        }

        // theoretically, we should never reach here but we assume no node was found
        throw new Exception('Could not find a node satisfying the requirements');
    }

    /**
     * This function is responsible for suspending an instance of the
     * service. This method is called when a order is expired or
     * suspended by an admin
     *
     * @return void
     */
    public function suspend(Order $order, ServerConnection $connection)
    {
        Server::makeRequest($connection->config, "/api/application/servers/{$order->external_id}/suspend", 'post');
    }

    /**
     * This function is responsible for unsuspending an instance of the
     * service. This method is called when a order is activated or
     * unsuspended by an admin
     *
     * @return void
     */
    public function unsuspend(Order $order, ServerConnection $connection)
    {
        Server::makeRequest($connection->config, "/api/application/servers/{$order->external_id}/unsuspend", 'post');
    }

    /**
     * This function is responsible for deleting an instance of the
     * service. This can be anything such as a server, vps or any other instance.
     *
     * @return void
     */
    public function terminate(Order $order, ServerConnection $connection)
    {
        Server::makeRequest($connection->config, "/api/application/servers/{$order->external_id}", 'delete');
    }

    public function upgrade(Order $order)
    {
        // TODO: Implement upgrade() method.
    }
}
