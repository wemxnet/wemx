<?php

namespace Extensions\Servers\Cpanel\Support;

use App\Models\ServerConnection;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WhmApi
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

    /**
     * @return array<string, mixed>
     */
    public function version(): array
    {
        return $this->whm('version');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPackages(): array
    {
        $data = $this->whm('listpkgs', ['want' => 'creatable']);
        $packages = $data['pkg'] ?? $data;

        return is_array($packages) ? array_values($packages) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listStyles(): array
    {
        $data = $this->whm('list_styles');
        $styles = $data['style'] ?? $data;

        return is_array($styles) ? array_values($styles) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function accountSummary(string $user): array
    {
        $data = $this->whm('accountsummary', ['user' => $user]);
        $accounts = $data['acct'] ?? [];

        if (isset($accounts[0]) && is_array($accounts[0])) {
            return $accounts[0];
        }

        return is_array($accounts) ? $accounts : $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createAccount(array $payload): array
    {
        return $this->whm('createacct', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function suspendAccount(string $user, ?string $reason = null): array
    {
        $payload = ['user' => $user];

        if ($reason) {
            $payload['reason'] = $reason;
        }

        return $this->whm('suspendacct', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function unsuspendAccount(string $user): array
    {
        return $this->whm('unsuspendacct', ['user' => $user]);
    }

    /**
     * @return array<string, mixed>
     */
    public function removeAccount(string $user): array
    {
        return $this->whm('removeacct', ['user' => $user]);
    }

    /**
     * @return array<string, mixed>
     */
    public function changePackage(string $user, string $package): array
    {
        return $this->whm('changepackage', [
            'user' => $user,
            'pkg' => $package,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function editQuota(string $user, int|string $quota): array
    {
        return $this->whm('editquota', [
            'user' => $user,
            'quota' => $quota,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function limitBandwidth(string $user, int|string $limit): array
    {
        return $this->whm('limitbw', [
            'user' => $user,
            'bwlimit' => $limit,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function changePassword(string $user, string $password): array
    {
        return $this->whm('passwd', [
            'user' => $user,
            'password' => $password,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function createUserSession(string $user, string $service = 'cpaneld'): array
    {
        return $this->whm('create_user_session', [
            'user' => $user,
            'service' => $service,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function uapi(string $user, string $module, string $function, array $params = []): mixed
    {
        $payload = array_merge($params, [
            'cpanel_jsonapi_user' => $user,
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => $module,
            'cpanel_jsonapi_func' => $function,
        ]);

        $response = $this->request('post', 'cpanel', $payload);
        $body = $response->json();

        if (! is_array($body)) {
            throw new Exception($this->friendlyError('cPanel UAPI request failed.'));
        }

        $result = $body['result'] ?? data_get($body, 'cpanelresult.result') ?? data_get($body, 'cpanelresult');

        if (is_array($result) && array_key_exists('status', $result)) {
            if ((int) $result['status'] !== 1) {
                throw new Exception($this->uapiError($module, $function, $result));
            }

            return $result['data'] ?? null;
        }

        $eventResult = data_get($body, 'cpanelresult.event.result');

        if ($eventResult !== null && (int) $eventResult !== 1) {
            throw new Exception($this->uapiError($module, $function, $body['cpanelresult'] ?? []));
        }

        return data_get($body, 'cpanelresult.data', $result);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function whm(string $function, array $params = []): array
    {
        $response = $this->request('post', $function, $params);
        $body = $response->json();

        if (! is_array($body)) {
            throw new Exception($this->friendlyError("WHM {$function} returned an invalid response."));
        }

        $metadata = $body['metadata'] ?? [];

        if (isset($metadata['result']) && (int) $metadata['result'] !== 1) {
            throw new Exception($this->whmError($function, $metadata, $response));
        }

        $data = $body['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    public function baseUrl(): string
    {
        $hostname = rtrim((string) ($this->credentials['hostname'] ?? ''), '/');

        if ($hostname === '') {
            throw new Exception('WHM hostname is not configured.');
        }

        if (! str_starts_with($hostname, 'https://') && ! str_starts_with($hostname, 'http://')) {
            $hostname = 'https://'.$hostname;
        }

        $parts = parse_url($hostname);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? $hostname;
        $port = $parts['port'] ?? (int) ($this->credentials['port'] ?? 2087);

        return "{$scheme}://{$host}:{$port}/json-api";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function request(string $method, string $function, array $data = []): Response
    {
        $method = strtolower($method);

        if (! in_array($method, ['get', 'post'], true)) {
            throw new Exception("Unsupported WHM HTTP method [{$method}].");
        }

        $url = $this->baseUrl().'/'.ltrim($function, '/');
        $payload = array_merge(['api.version' => 1], $data);
        $request = $this->http();

        $response = match ($method) {
            'get' => $request->get($url, $payload),
            'post' => $request->asForm()->post($url, $payload),
        };

        if ($response->failed()) {
            throw new Exception($this->httpError($function, $response));
        }

        return $response;
    }

    protected function http(): PendingRequest
    {
        $verifySsl = (string) ($this->credentials['verify_ssl'] ?? '1') === '1';

        return Http::acceptJson()
            ->timeout(45)
            ->connectTimeout(10)
            ->retry(2, 250, throw: false)
            ->withOptions(['verify' => $verifySsl])
            ->withHeaders([
                'Authorization' => $this->authorizationHeader(),
            ]);
    }

    protected function authorizationHeader(): string
    {
        $username = trim((string) ($this->credentials['username'] ?? ''));

        if ($username === '') {
            throw new Exception('WHM username is not configured.');
        }

        $token = trim((string) ($this->credentials['api_token'] ?? $this->credentials['token'] ?? ''));

        if ($token !== '') {
            return "whm {$username}:{$token}";
        }

        $accessHash = preg_replace('/\s+/', '', (string) ($this->credentials['access_hash'] ?? '')) ?? '';

        if ($accessHash !== '') {
            return "WHM {$username}:{$accessHash}";
        }

        throw new Exception('Provide a WHM API token or access hash.');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function whmError(string $function, array $metadata, Response $response): string
    {
        $reason = trim((string) ($metadata['reason'] ?? $response->body()));
        $reason = Str::limit(strip_tags($reason), 400);

        if ((string) ($this->credentials['debug_mode'] ?? '0') === '1') {
            return "WHM {$function} failed ({$response->status()}): {$reason}";
        }

        return $this->friendlyError($reason !== '' ? $reason : "WHM {$function} failed.");
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function uapiError(string $module, string $function, array $result): string
    {
        $errors = $result['errors'] ?? $result['error'] ?? null;

        if (is_array($errors)) {
            $errors = collect($errors)->filter()->implode('; ');
        }

        $message = trim((string) ($errors ?: ($result['messages'][0] ?? 'The cPanel request failed.')));
        $message = Str::limit(strip_tags($message), 400);

        if ((string) ($this->credentials['debug_mode'] ?? '0') === '1') {
            return "cPanel UAPI {$module}::{$function} failed: {$message}";
        }

        return $this->friendlyError($message);
    }

    protected function httpError(string $function, Response $response): string
    {
        $body = $response->json();
        $message = data_get($body, 'metadata.reason')
            ?: data_get($body, 'cpanelresult.error')
            ?: data_get($body, 'error')
            ?: $response->body();

        if (is_array($message)) {
            $message = collect($message)->implode('; ');
        }

        $message = Str::limit(trim(strip_tags((string) $message)), 400);

        if ((string) ($this->credentials['debug_mode'] ?? '0') === '1') {
            return "WHM request to {$function} failed ({$response->status()}): {$message}";
        }

        return $this->friendlyError($message !== '' ? $message : 'The hosting panel could not be reached.');
    }

    protected function friendlyError(string $message): string
    {
        $message = trim(strip_tags($message));

        return $message !== '' ? $message : 'The hosting panel request failed.';
    }
}
