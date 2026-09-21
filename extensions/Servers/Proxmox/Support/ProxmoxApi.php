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

    /**
     * @return array{scheme: string, authority: string}
     */
    public function panelBase(): array
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
        $host = $parts['host'] ?? ltrim((string) ($parts['path'] ?? ''), '/');

        if ($host === '') {
            throw new Exception('Proxmox hostname is not configured.');
        }

        $authority = isset($parts['port'])
            ? "{$host}:{$parts['port']}"
            : $host;

        return [
            'scheme' => $scheme,
            'authority' => $authority,
        ];
    }

    public function baseUrl(): string
    {
        $panel = $this->panelBase();

        return "{$panel['scheme']}://{$panel['authority']}/api2/json";
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

        return $this->withTicket($request);
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
            throw new Exception('Proxmox username and password are required.');
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

    protected function data(Response $response): mixed
    {
        return $response->json('data');
    }

    protected function errorMessage(string $endpoint, Response $response): string
    {
        $status = $response->status();
        $message = $this->extractErrorMessage($response);
        $endpointLabel = $this->endpointLabel($endpoint);
        $details = $message !== ''
            ? $message
            : $this->statusHint($status, $endpoint);

        return "Proxmox {$endpointLabel} failed (HTTP {$status}): {$details}";
    }

    protected function extractErrorMessage(Response $response): string
    {
        $body = $response->json();

        if (! is_array($body)) {
            $raw = trim($response->body());

            if ($raw === '' || in_array($raw, ['{"data":null}', '{"data": null}'], true)) {
                return '';
            }

            return Str::limit($raw, 500);
        }

        $errors = data_get($body, 'errors');

        if (is_array($errors) && $errors !== []) {
            return Str::limit(collect($errors)->map(function ($value, $key) {
                if (is_array($value)) {
                    $details = collect($value)->flatten()->filter()->implode(', ');

                    return is_string($key) ? "{$key}: {$details}" : $details;
                }

                return is_string($key) ? "{$key}: {$value}" : (string) $value;
            })->implode('; '), 500);
        }

        foreach (['message', 'data'] as $field) {
            $value = data_get($body, $field);

            if (is_string($value) && trim($value) !== '') {
                return Str::limit(trim($value), 500);
            }
        }

        return '';
    }

    protected function endpointLabel(string $endpoint): string
    {
        if (preg_match('#^/nodes/([^/]+)/qemu/(\d+)/config$#', $endpoint, $matches)) {
            return "template lookup on node [{$matches[1]}] for VMID [{$matches[2]}]";
        }

        if (preg_match('#^/nodes/([^/]+)/qemu/(\d+)/#', $endpoint, $matches)) {
            return "virtual machine request on node [{$matches[1]}] for VMID [{$matches[2]}]";
        }

        if ($endpoint === '/nodes') {
            return 'node list request';
        }

        if ($endpoint === '/cluster/resources') {
            return 'cluster resource request';
        }

        if ($endpoint === '/version') {
            return 'version request';
        }

        if ($endpoint === '/access/ticket') {
            return 'authentication request';
        }

        return 'API request to ['.$endpoint.']';
    }

    protected function statusHint(int $status, string $endpoint): string
    {
        return match (true) {
            $status === 401 => 'Authentication failed. Check the Proxmox username and password.',
            $status === 403 => 'Access denied. The Proxmox account may not have permission for this action.',
            $status === 404 => 'The requested resource was not found on the Proxmox cluster.',
            $status === 500 && str_contains($endpoint, '/qemu/') && str_contains($endpoint, '/config') => 'The template VM may not exist on that node, or the Proxmox account may lack permission to read it.',
            $status >= 500 => 'The Proxmox panel returned an unexpected server error.',
            default => 'The Proxmox panel rejected the request.',
        };
    }
}
