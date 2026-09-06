<?php

namespace Extensions\Servers\Proxmox\Support;

use App\Models\ServerConnection;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProxmoxApi
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(
        protected array $credentials,
    ) {}

    /**
     * @param  array<string, mixed>  $credentials
     */
    public static function make(array $credentials): self
    {
        return new self($credentials);
    }

    public static function fromConnection(ServerConnection $connection): self
    {
        return new self($connection->config ?? []);
    }

    public function version(): array
    {
        return $this->data($this->request('get', '/version'));
    }

    public function nodes(): array
    {
        return $this->data($this->request('get', '/nodes'));
    }

    public function nodeStatus(string $node): array
    {
        return $this->data($this->request('get', "/nodes/{$node}/status"));
    }

    public function storages(?string $node = null, ?string $content = null): array
    {
        $query = [];

        if ($content) {
            $query['content'] = $content;
        }

        $path = $node ? "/nodes/{$node}/storage" : '/storage';

        return $this->data($this->request('get', $path, $query));
    }

    public function nextId(?int $vmid = null): int
    {
        $query = [];

        if ($vmid) {
            $query['vmid'] = $vmid;
        }

        return (int) $this->data($this->request('get', '/cluster/nextid', $query));
    }

    public function clusterResources(?string $type = null): array
    {
        $query = [];

        if ($type) {
            $query['type'] = $type;
        }

        return $this->data($this->request('get', '/cluster/resources', $query));
    }

    public function qemuConfig(string $node, int $vmid): array
    {
        return $this->data($this->request('get', "/nodes/{$node}/qemu/{$vmid}/config"));
    }

    public function qemuStatus(string $node, int $vmid): array
    {
        return $this->data($this->request('get', "/nodes/{$node}/qemu/{$vmid}/status/current"));
    }

    public function rrdData(string $node, int $vmid, string $timeframe = 'hour'): array
    {
        return $this->data($this->request('get', "/nodes/{$node}/qemu/{$vmid}/rrddata", [
            'timeframe' => $timeframe,
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function cloneVm(string $node, int $templateVmid, array $payload): string
    {
        return (string) $this->data($this->request('post', "/nodes/{$node}/qemu/{$templateVmid}/clone", $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateConfig(string $node, int $vmid, array $payload): mixed
    {
        return $this->data($this->request('put', "/nodes/{$node}/qemu/{$vmid}/config", $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resizeDisk(string $node, int $vmid, array $payload): mixed
    {
        return $this->data($this->request('put', "/nodes/{$node}/qemu/{$vmid}/resize", $payload));
    }

    public function start(string $node, int $vmid): mixed
    {
        return $this->data($this->request('post', "/nodes/{$node}/qemu/{$vmid}/status/start"));
    }

    public function stop(string $node, int $vmid): mixed
    {
        return $this->data($this->request('post', "/nodes/{$node}/qemu/{$vmid}/status/stop"));
    }

    public function shutdown(string $node, int $vmid): mixed
    {
        return $this->data($this->request('post', "/nodes/{$node}/qemu/{$vmid}/status/shutdown"));
    }

    public function reboot(string $node, int $vmid): mixed
    {
        return $this->data($this->request('post', "/nodes/{$node}/qemu/{$vmid}/status/reboot"));
    }

    public function suspend(string $node, int $vmid): mixed
    {
        return $this->data($this->request('post', "/nodes/{$node}/qemu/{$vmid}/status/suspend"));
    }

    public function resume(string $node, int $vmid): mixed
    {
        return $this->data($this->request('post', "/nodes/{$node}/qemu/{$vmid}/status/resume"));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function deleteVm(string $node, int $vmid, array $query = []): mixed
    {
        return $this->data($this->request('delete', "/nodes/{$node}/qemu/{$vmid}", array_merge([
            'purge' => 1,
            'destroy-unreferenced-disks' => 1,
        ], $query)));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createSnapshot(string $node, int $vmid, array $payload): mixed
    {
        return $this->data($this->request('post', "/nodes/{$node}/qemu/{$vmid}/snapshot", $payload));
    }

    public function snapshots(string $node, int $vmid): array
    {
        return $this->data($this->request('get', "/nodes/{$node}/qemu/{$vmid}/snapshot"));
    }

    public function deleteSnapshot(string $node, int $vmid, string $name): mixed
    {
        return $this->data($this->request('delete', "/nodes/{$node}/qemu/{$vmid}/snapshot/{$name}"));
    }

    public function rollbackSnapshot(string $node, int $vmid, string $name): mixed
    {
        return $this->data($this->request('post', "/nodes/{$node}/qemu/{$vmid}/snapshot/{$name}/rollback"));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function setUserPassword(string $node, int $vmid, array $payload): mixed
    {
        return $this->data($this->request('post', "/nodes/{$node}/qemu/{$vmid}/agent/set-user-password", $payload));
    }

    public function guestNetworkInterfaces(string $node, int $vmid): array
    {
        return $this->data($this->request('get', "/nodes/{$node}/qemu/{$vmid}/agent/network-get-interfaces"));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function vncProxy(string $node, int $vmid, array $payload = []): array
    {
        return $this->data($this->request('post', "/nodes/{$node}/qemu/{$vmid}/vncproxy", $payload));
    }

    public function taskStatus(string $node, string $upid): array
    {
        return $this->data($this->request('get', "/nodes/{$node}/tasks/".rawurlencode($upid).'/status'));
    }

    public function waitForTask(?string $upid, string $node, int $timeoutSeconds = 300): void
    {
        if (! $upid || ! str_starts_with($upid, 'UPID:')) {
            return;
        }

        $deadline = time() + $timeoutSeconds;

        while (time() < $deadline) {
            $status = $this->taskStatus($node, $upid);
            $state = $status['status'] ?? null;

            if ($state === 'stopped') {
                $exit = $status['exitstatus'] ?? 'unknown';

                if ($exit !== 'OK') {
                    throw new Exception("Proxmox task failed with status [{$exit}].");
                }

                return;
            }

            usleep(500_000);
        }

        throw new Exception('Timed out waiting for the Proxmox task to finish.');
    }

    public function baseUrl(): string
    {
        $hostname = rtrim((string) ($this->credentials['hostname'] ?? ''), '/');

        if ($hostname === '') {
            throw new Exception('Proxmox hostname is not configured.');
        }

        if (! str_starts_with($hostname, 'http://') && ! str_starts_with($hostname, 'https://')) {
            $hostname = 'https://'.$hostname;
        }

        $parts = parse_url($hostname);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? $hostname;
        $port = $parts['port'] ?? (int) ($this->credentials['port'] ?? 8006);

        return "{$scheme}://{$host}:{$port}/api2/json";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function request(string $method, string $endpoint, array $data = []): Response
    {
        $method = strtolower($method);

        if (! in_array($method, ['get', 'post', 'put', 'delete'], true)) {
            throw new Exception("Unsupported Proxmox HTTP method [{$method}].");
        }

        $url = $this->baseUrl().'/'.ltrim($endpoint, '/');
        $request = $this->http();

        $response = match ($method) {
            'get' => $request->get($url, $data),
            'delete' => $request->delete($url, $data),
            default => $request->asForm()->{$method}($url, $data),
        };

        if ($response->failed()) {
            throw new Exception($this->errorMessage($endpoint, $response));
        }

        return $response;
    }

    protected function http(): PendingRequest
    {
        $verifySsl = (string) ($this->credentials['verify_ssl'] ?? '0') === '1';

        $request = Http::acceptJson()
            ->timeout(30)
            ->connectTimeout(10)
            ->retry(2, 250, throw: false)
            ->withOptions(['verify' => $verifySsl]);

        if (($this->credentials['auth_type'] ?? 'token') === 'password') {
            return $this->withTicket($request);
        }

        return $request->withHeaders([
            'Authorization' => 'PVEAPIToken='.$this->apiToken(),
        ]);
    }

    protected function withTicket(PendingRequest $request): PendingRequest
    {
        $ticket = $this->ticket();

        return $request
            ->withCookies(['PVEAuthCookie' => $ticket['ticket']], parse_url($this->baseUrl(), PHP_URL_HOST))
            ->withHeaders([
                'CSRFPreventionToken' => $ticket['CSRFPreventionToken'],
            ]);
    }

    /**
     * @return array{ticket: string, CSRFPreventionToken: string}
     */
    protected function ticket(): array
    {
        $username = $this->credentials['username'] ?? '';
        $password = $this->credentials['password'] ?? '';

        if ($username === '' || $password === '') {
            throw new Exception('Proxmox username and password are required for password authentication.');
        }

        $verifySsl = (string) ($this->credentials['verify_ssl'] ?? '0') === '1';

        $response = Http::acceptJson()
            ->timeout(15)
            ->connectTimeout(10)
            ->withOptions(['verify' => $verifySsl])
            ->asForm()
            ->post($this->baseUrl().'/access/ticket', [
                'username' => $username,
                'password' => $password,
            ]);

        if ($response->failed()) {
            throw new Exception($this->errorMessage('/access/ticket', $response));
        }

        $data = $response->json('data');

        if (! is_array($data) || empty($data['ticket']) || empty($data['CSRFPreventionToken'])) {
            throw new Exception('Proxmox did not return a valid authentication ticket.');
        }

        return $data;
    }

    protected function apiToken(): string
    {
        $username = (string) ($this->credentials['username'] ?? '');
        $tokenId = (string) ($this->credentials['token_id'] ?? '');
        $tokenSecret = (string) ($this->credentials['token_secret'] ?? $this->credentials['api_token'] ?? '');

        if ($tokenSecret !== '' && str_contains($tokenSecret, '=')) {
            return $tokenSecret;
        }

        if ($username === '' || $tokenId === '' || $tokenSecret === '') {
            throw new Exception('Proxmox API token credentials are incomplete. Provide username, token ID, and token secret.');
        }

        return "{$username}!{$tokenId}={$tokenSecret}";
    }

    protected function data(Response $response): mixed
    {
        return $response->json('data');
    }

    protected function errorMessage(string $endpoint, Response $response): string
    {
        $body = $response->json();
        $message = data_get($body, 'errors') ?: data_get($body, 'message') ?: $response->body();

        if (is_array($message)) {
            $message = collect($message)->map(fn ($value, $key) => is_string($key) ? "{$key}: {$value}" : $value)->implode('; ');
        }

        $message = Str::limit(trim((string) $message), 500);

        if ((string) ($this->credentials['debug_mode'] ?? '0') === '1') {
            return "Proxmox API request to {$endpoint} failed ({$response->status()}): {$message}";
        }

        return "Proxmox API request failed ({$response->status()}): {$message}";
    }
}
