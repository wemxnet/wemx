<?php

namespace Extensions\Modules\Marketplace\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

final class MarketplaceUploads
{
    public static function assertZip(UploadedFile $file): void
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_file($path)) {
            throw ValidationException::withMessages([
                'file' => 'Upload a .zip archive.',
            ]);
        }

        $header = file_get_contents($path, false, null, 0, 4);

        if (! in_array($header, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true)) {
            throw ValidationException::withMessages([
                'file' => 'Upload a .zip archive.',
            ]);
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages([
                'file' => 'The zip archive could not be read.',
            ]);
        }

        $maxEntries = 2000;
        $maxUncompressed = 100 * 1024 * 1024;
        $uncompressed = 0;

        try {
            if ($zip->numFiles > $maxEntries) {
                throw ValidationException::withMessages([
                    'file' => 'The zip archive contains too many files.',
                ]);
            }

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));

                if (
                    $name === ''
                    || str_contains($name, "\0")
                    || str_contains($name, '..')
                    || str_starts_with($name, '/')
                ) {
                    throw ValidationException::withMessages([
                        'file' => 'The zip archive contains an unsafe file path.',
                    ]);
                }

                $uncompressed += (int) ($stat['size'] ?? 0);

                if ($uncompressed > $maxUncompressed) {
                    throw ValidationException::withMessages([
                        'file' => 'The zip archive is too large when extracted.',
                    ]);
                }
            }
        } finally {
            $zip->close();
        }
    }

    public static function safeZipName(string $originalName): string
    {
        $name = basename(str_replace('\\', '/', $originalName));
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'package.zip';

        if (! str_ends_with(strtolower($name), '.zip')) {
            $name .= '.zip';
        }

        return $name;
    }

    /**
     * @return array{binary: string, extension: string}
     */
    public static function reencodeImage(string $path, int $type): array
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if ($image === false) {
            throw ValidationException::withMessages([
                'icon' => 'The icon must be a valid JPEG, PNG, GIF, or WebP image.',
            ]);
        }

        $extension = match ($type) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_WEBP => 'webp',
            default => 'png',
        };

        ob_start();

        $written = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, null, 90),
            IMAGETYPE_PNG => imagepng($image),
            IMAGETYPE_GIF => imagegif($image),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($image, null, 90) : false,
            default => false,
        };

        $binary = ob_get_clean();
        imagedestroy($image);

        if ($written !== true || ! is_string($binary) || $binary === '') {
            throw ValidationException::withMessages([
                'icon' => 'The icon must be a valid JPEG, PNG, GIF, or WebP image.',
            ]);
        }

        return [
            'binary' => $binary,
            'extension' => $extension,
        ];
    }
}
