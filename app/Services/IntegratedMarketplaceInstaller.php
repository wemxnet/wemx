<?php

namespace App\Services;

use App\Models\Extension;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class IntegratedMarketplaceInstaller
{
    public function install(string $slug, int $versionId): string
    {
        $resource = $this->resource($slug);
        $version = $this->version($resource, $versionId);
        $this->assertCompatible((string) ($version['wemx_version'] ?? ''));

        $zipPath = $this->download($version, $resource);
        $destination = $this->extract($zipPath, $version);
        @unlink($zipPath);

        $installed = $this->enableExtension($destination);

        return $installed
            ? sprintf('%s %s was installed.', $resource['name'] ?? 'Resource', $version['version'])
            : sprintf('%s %s was extracted to %s.', $resource['name'] ?? 'Resource', $version['version'], $this->relativePath($destination));
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(string $slug): array
    {
        try {
            $response = $this->http()->get('/api/v1/marketplace/resources/'.rawurlencode(trim($slug)));
        } catch (ConnectionException) {
            throw new RuntimeException('The marketplace could not be reached.');
        }

        if (! $response->successful() || ! is_array($response->json('data'))) {
            throw new RuntimeException('This resource could not be loaded from the marketplace.');
        }

        return $response->json('data');
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return array<string, mixed>
     */
    private function version(array $resource, int $versionId): array
    {
        $versions = is_array($resource['versions'] ?? null) ? $resource['versions'] : [];

        foreach ($versions as $version) {
            if (! is_array($version) || (int) ($version['id'] ?? 0) !== $versionId) {
                continue;
            }

            if (empty($version['integrated_marketplace'])) {
                throw new RuntimeException('This version is not available on the integrated marketplace.');
            }

            return $version;
        }

        throw new RuntimeException('This version is not available on the integrated marketplace.');
    }

    public function supportsCurrentVersion(string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        $installed = $this->normalizeVersion((string) config('app.version'));
        $required = $this->normalizeVersion($constraint);

        return $installed === $required
            || str_starts_with($installed, $required.'.')
            || str_starts_with($installed, $required.'-');
    }

    private function assertCompatible(string $constraint): void
    {
        if ($this->supportsCurrentVersion($constraint)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'This version requires WemX %s. This site is running %s.',
            trim($constraint),
            config('app.version'),
        ));
    }

    /**
     * @param  array<string, mixed>  $version
     * @param  array<string, mixed>  $resource
     */
    private function download(array $version, array $resource): string
    {
        $path = storage_path('app/marketplace-installs/'.Str::uuid().'.zip');
        File::ensureDirectoryExists(dirname($path));

        $query = [];

        if (($resource['price'] ?? 'Free') !== 'Free') {
            throw new RuntimeException('Paid resources need a license before they can be installed from the marketplace.');
        }

        try {
            $response = $this->http()
                ->withHeaders(['Accept' => 'application/zip, application/json'])
                ->get('/api/v1/marketplace/resources/download/'.$version['id'], $query);
        } catch (ConnectionException) {
            throw new RuntimeException('The marketplace could not be reached.');
        }

        if (! $response->successful() || $response->body() === '') {
            $message = $response->json('message');

            throw new RuntimeException(is_string($message) && $message !== ''
                ? $message
                : 'The version could not be downloaded.');
        }

        File::put($path, $response->body());

        $checksum = (string) ($version['checksum'] ?? '');

        if ($checksum !== '' && ! hash_equals($checksum, hash_file('sha256', $path))) {
            @unlink($path);

            throw new RuntimeException('The downloaded file did not match the marketplace checksum.');
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $version
     */
    private function extract(string $zipPath, array $version): string
    {
        $extractPath = $this->safeRelativePath((string) ($version['extract_path'] ?? ''));
        $folder = $this->safeFolderName((string) ($version['rename_extract_to'] ?? ''));
        $temp = storage_path('app/marketplace-installs/'.Str::uuid());
        File::ensureDirectoryExists($temp);

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            File::deleteDirectory($temp);

            throw new RuntimeException('The downloaded file is not a valid zip archive.');
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = str_replace('\\', '/', (string) $zip->getNameIndex($index));

                if ($name === '' || str_contains($name, "\0") || str_contains($name, '..') || str_starts_with($name, '/')) {
                    throw new RuntimeException('The archive contains an unsafe file path.');
                }
            }

            if (! $zip->extractTo($temp)) {
                throw new RuntimeException('The archive could not be extracted.');
            }
        } finally {
            $zip->close();
        }

        $package = $this->packageRoot($temp);

        if ($folder === '') {
            $folder = basename($package);
        }

        $folder = $this->safeFolderName($folder);
        $destination = base_path($extractPath.DIRECTORY_SEPARATOR.$folder);
        $parent = dirname($destination);

        if (! is_dir($parent) && ! File::makeDirectory($parent, 0755, true)) {
            File::deleteDirectory($temp);

            throw new RuntimeException('The extract folder could not be created.');
        }

        $parentReal = realpath($parent);
        $baseReal = realpath(base_path());

        if ($parentReal === false || $baseReal === false || ! str_starts_with($parentReal, $baseReal)) {
            File::deleteDirectory($temp);

            throw new RuntimeException('The extract path must stay inside the application.');
        }

        if (is_dir($destination)) {
            File::deleteDirectory($destination);
        }

        File::moveDirectory($package, $destination);
        File::deleteDirectory($temp);

        return $destination;
    }

    private function enableExtension(string $destination): bool
    {
        $relative = $this->relativePath($destination);
        $parts = explode('/', $relative);

        if (($parts[0] ?? '') !== 'extensions' || count($parts) < 3) {
            return false;
        }

        $class = 'Extensions\\'.$parts[1].'\\'.$parts[2].'\\'.Str::singular($parts[1]);

        if (! class_exists($class)) {
            return false;
        }

        Extension::discover();

        $extension = Extension::query()->where('namespace', $class)->first();

        if (! $extension) {
            return false;
        }

        $extension->enable();
        Artisan::call('extension:migrate', ['name' => $extension->identifier]);

        return true;
    }

    private function packageRoot(string $temp): string
    {
        $items = array_values(array_diff(scandir($temp) ?: [], ['.', '..']));

        if (count($items) === 1 && is_dir($temp.DIRECTORY_SEPARATOR.$items[0])) {
            return $temp.DIRECTORY_SEPARATOR.$items[0];
        }

        return $temp;
    }

    private function safeRelativePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        if ($path === '' || str_contains($path, '..') || ! preg_match('#^(extensions|resources)(/[A-Za-z0-9._-]+)+$#', $path)) {
            throw new RuntimeException('This version does not have a safe extract path.');
        }

        return $path;
    }

    private function safeFolderName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        if (! preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            throw new RuntimeException('This version does not have a safe folder name.');
        }

        return $name;
    }

    private function normalizeVersion(string $version): string
    {
        return ltrim(strtolower(trim($version)), 'v');
    }

    private function relativePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', Str::after($path, base_path())), '/');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.marketplace.url'), '/'))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(60);
    }
}
