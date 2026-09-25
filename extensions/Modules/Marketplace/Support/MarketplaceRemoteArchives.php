<?php

namespace Extensions\Modules\Marketplace\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class MarketplaceRemoteArchives
{
    /**
     * @var list<string>
     */
    private const SOURCE_HOSTS = [
        'github.com',
        'codeload.github.com',
        'gitlab.com',
    ];

    /**
     * @var list<string>
     */
    private const REDIRECT_HOSTS = [
        'github.com',
        'codeload.github.com',
        'objects.githubusercontent.com',
        'release-assets.githubusercontent.com',
        'gitlab.com',
    ];

    public static function fetch(string $url): string
    {
        self::assertSourceUrl($url);

        $current = $url;
        $response = null;

        for ($hop = 0; $hop < 5; $hop++) {
            try {
                $response = Http::withOptions(['allow_redirects' => false])
                    ->withHeaders([
                        'Accept' => 'application/zip',
                        'User-Agent' => 'WemX',
                    ])
                    ->timeout(30)
                    ->get($current);
            } catch (ConnectionException) {
                throw ValidationException::withMessages([
                    'archive_url' => 'The zip link could not be reached.',
                ]);
            }

            if (! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                break;
            }

            $location = $response->header('Location');

            if (! is_string($location) || $location === '') {
                throw ValidationException::withMessages([
                    'archive_url' => 'The zip link could not be reached.',
                ]);
            }

            $current = self::absoluteUrl($current, $location);
            self::assertHost($current, self::REDIRECT_HOSTS);
        }

        $maxBytes = MarketplaceLimits::maxUploadKilobytes() * 1024;

        if ($response === null || ! $response->successful() || strlen($response->body()) === 0 || strlen($response->body()) > $maxBytes) {
            throw ValidationException::withMessages([
                'archive_url' => 'The link must return a zip file within the upload size limit.',
            ]);
        }

        $path = tempnam(sys_get_temp_dir(), 'mkt');

        if ($path === false) {
            throw ValidationException::withMessages([
                'archive_url' => 'The zip link could not be downloaded.',
            ]);
        }

        file_put_contents($path, $response->body());

        return $path;
    }

    public static function assertSourceUrl(string $url): void
    {
        self::assertHost($url, self::SOURCE_HOSTS);

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        $trusted = match ($host) {
            'github.com' => self::isGitHubArchive($path),
            'codeload.github.com' => (bool) preg_match('#^/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+/zip/(refs/(heads|tags)/)?[A-Za-z0-9._/-]+$#', $path),
            'gitlab.com' => self::isGitLabArchive($path),
            default => false,
        };

        if (! $trusted || isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages([
                'archive_url' => 'Use a GitHub or GitLab link that downloads a zip archive.',
            ]);
        }
    }

    private static function isGitHubArchive(string $path): bool
    {
        return (bool) preg_match('#^/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+/archive/(refs/(heads|tags)/)?[A-Za-z0-9._/-]+\.zip$#', $path)
            || (bool) preg_match('#^/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+/releases/download/[^/]+/[^/]+\.zip$#', $path);
    }

    private static function isGitLabArchive(string $path): bool
    {
        return (bool) preg_match('#^/(?:[A-Za-z0-9_.-]+/){1,20}-/archive/[^/]+/[^/]+\.zip$#', $path)
            || (bool) preg_match('#^/api/v4/projects/\d+/repository/archive\.zip$#', $path);
    }

    /**
     * @param  list<string>  $hosts
     */
    private static function assertHost(string $url, array $hosts): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || ! in_array($host, $hosts, true)) {
            throw ValidationException::withMessages([
                'archive_url' => 'Use a GitHub or GitLab link that downloads a zip archive.',
            ]);
        }
    }

    private static function absoluteUrl(string $current, string $location): string
    {
        if (str_starts_with($location, 'https://')) {
            return $location;
        }

        $parts = parse_url($current);

        return 'https://'.($parts['host'] ?? '').'/'.ltrim($location, '/');
    }
}
