<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IntegratedMarketplace
{
    public const CATEGORY_SLUGS = ['server', 'module', 'payment-gateway'];

    public const PER_PAGE = 18;

    public const CATALOG_CACHE_TTL_SECONDS = 600;

    public const RESOURCE_CACHE_TTL_SECONDS = 1800;

    /**
     * @param  array{search?: string, category?: string|null, sort_by?: string, page?: int, per_page?: int}  $filters
     * @return array{
     *     resources: list<array<string, mixed>>,
     *     featured: list<array<string, mixed>>,
     *     categories: list<array{slug: string, name: string}>,
     *     page: int,
     *     last_page: int,
     *     total: int,
     *     error: string|null
     * }
     */
    public function catalog(array $filters = []): array
    {
        $query = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'category' => (string) ($filters['category'] ?? ''),
            'sort_by' => (string) ($filters['sort_by'] ?? 'popular'),
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'per_page' => self::PER_PAGE,
        ];

        if ($query['category'] === '' || ! in_array($query['category'], self::CATEGORY_SLUGS, true)) {
            unset($query['category']);
        }

        if ($query['search'] === '') {
            unset($query['search']);
        }

        $key = 'integrated-marketplace.catalog.'.md5((string) json_encode($query));

        return $this->remember($key, fn (): array => $this->fetchCatalog($query), self::CATALOG_CACHE_TTL_SECONDS);
    }

    /**
     * @return array{resource: array<string, mixed>|null, error: string|null}
     */
    public function resource(string $slug): array
    {
        $slug = trim($slug);

        return $this->remember(
            'integrated-marketplace.resource.'.$slug,
            fn (): array => $this->fetchResource($slug),
            self::RESOURCE_CACHE_TTL_SECONDS,
        );
    }

    public function forgetResource(string $slug): void
    {
        Cache::forget('integrated-marketplace.resource.'.trim($slug));
    }

    public function recordView(string $slug): void
    {
        $slug = trim($slug);

        if ($slug === '') {
            return;
        }

        try {
            $response = $this->request()->post('/api/v1/marketplace/resources/'.rawurlencode($slug).'/view', [
                'visitor' => hash('sha256', 'integrated:'.rtrim((string) config('app.url'), '/').':'.(auth()->id() ?? 'guest')),
            ]);
        } catch (ConnectionException) {
            return;
        }

        if ($response->successful()) {
            $this->forgetResource($slug);
        }
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function remember(string $key, callable $callback, int $ttl): array
    {
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $payload = $callback();

        if (($payload['error'] ?? null) !== null) {
            return $payload;
        }

        if (array_key_exists('resource', $payload) && $payload['resource'] === null) {
            return $payload;
        }

        Cache::put($key, $payload, $ttl);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{
     *     resources: list<array<string, mixed>>,
     *     featured: list<array<string, mixed>>,
     *     categories: list<array{slug: string, name: string}>,
     *     page: int,
     *     last_page: int,
     *     total: int,
     *     error: string|null
     * }
     */
    private function fetchCatalog(array $query): array
    {
        $empty = [
            'resources' => [],
            'featured' => [],
            'categories' => [],
            'page' => 1,
            'last_page' => 1,
            'total' => 0,
            'error' => null,
        ];

        try {
            $response = $this->request()->get('/api/v1/marketplace/resources', $query);
        } catch (ConnectionException) {
            $empty['error'] = 'The marketplace could not be reached. Try again in a moment.';

            return $empty;
        }

        if (! $response->successful()) {
            $empty['error'] = 'The marketplace returned an unexpected response.';

            return $empty;
        }

        $json = $response->json();

        $resources = is_array($json['data'] ?? null) ? $json['data'] : [];
        $featured = is_array($json['featured'] ?? null) ? $json['featured'] : [];
        $categories = is_array($json['categories'] ?? null) ? $json['categories'] : [];

        return [
            'resources' => array_values(array_filter(array_map($this->summary(...), $resources), $this->isIntegratedCategory(...))),
            'featured' => array_values(array_filter(array_map($this->summary(...), $featured), $this->isIntegratedCategory(...))),
            'categories' => array_values(array_filter($categories, function (mixed $category): bool {
                return is_array($category) && in_array($category['slug'] ?? null, self::CATEGORY_SLUGS, true);
            })),
            'page' => (int) ($json['current_page'] ?? 1),
            'last_page' => max(1, (int) ($json['last_page'] ?? 1)),
            'total' => (int) ($json['total'] ?? 0),
            'error' => null,
        ];
    }

    /**
     * @return array{resource: array<string, mixed>|null, error: string|null}
     */
    private function fetchResource(string $slug): array
    {
        try {
            $response = $this->request()->get('/api/v1/marketplace/resources/'.rawurlencode($slug));
        } catch (ConnectionException) {
            return [
                'resource' => null,
                'error' => 'The marketplace could not be reached. Try again in a moment.',
            ];
        }

        if ($response->notFound()) {
            return [
                'resource' => null,
                'error' => null,
            ];
        }

        if (! $response->successful()) {
            return [
                'resource' => null,
                'error' => 'The marketplace returned an unexpected response.',
            ];
        }

        $data = $response->json('data');

        if (! is_array($data) || ! $this->isIntegratedCategory($data)) {
            return [
                'resource' => null,
                'error' => is_array($data) ? null : 'This resource could not be loaded.',
            ];
        }

        return [
            'resource' => $data,
            'error' => null,
        ];
    }

    private function isIntegratedCategory(mixed $resource): bool
    {
        if (! is_array($resource)) {
            return false;
        }

        $category = is_array($resource['category'] ?? null) ? $resource['category'] : [];

        return in_array($category['slug'] ?? null, self::CATEGORY_SLUGS, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(mixed $resource): array
    {
        if (! is_array($resource)) {
            return [];
        }

        $category = is_array($resource['category'] ?? null) ? $resource['category'] : [];
        $user = is_array($resource['user'] ?? null) ? $resource['user'] : [];

        return [
            'id' => $resource['id'] ?? null,
            'name' => $resource['name'] ?? '',
            'slug' => $resource['slug'] ?? '',
            'short_description' => $resource['short_description'] ?? '',
            'icon' => $resource['icon'] ?? null,
            'initials' => $resource['initials'] ?? '',
            'price' => $resource['price'] ?? '',
            'featured' => (bool) ($resource['featured'] ?? false),
            'official' => (bool) ($resource['official'] ?? false),
            'views' => (int) ($resource['views'] ?? 0),
            'downloads' => (int) ($resource['downloads'] ?? 0),
            'purchases' => (int) ($resource['purchases'] ?? 0),
            'reviews_count' => (int) ($resource['reviews_count'] ?? 0),
            'reviews_avg' => (float) ($resource['reviews_avg'] ?? 0),
            'latest_version' => $resource['latest_version'] ?? ($resource['versions'][0]['version'] ?? null),
            'view_url' => $resource['view_url'] ?? null,
            'category' => [
                'slug' => $category['slug'] ?? null,
                'name' => $category['name'] ?? null,
            ],
            'user' => [
                'username' => $user['username'] ?? null,
                'avatar' => $user['avatar'] ?? null,
            ],
        ];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.marketplace.url'), '/'))
            ->acceptJson()
            ->withOptions([
                'allow_redirects' => [
                    'strict' => true,
                    'referer' => true,
                    'protocols' => ['http', 'https'],
                    'max' => 5,
                ],
            ])
            ->connectTimeout(3)
            ->timeout(8)
            ->retry(2, 200, function ($exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError());
            }, throw: false);
    }
}
