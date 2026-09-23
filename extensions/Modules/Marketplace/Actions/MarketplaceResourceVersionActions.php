<?php

namespace Extensions\Modules\Marketplace\Actions;

use App\Actions\Action;
use Extensions\Modules\Marketplace\Actions\Concerns\AuthorizesMarketplaceStaff;
use Extensions\Modules\Marketplace\Enums\ResourceStatus;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Enums\VersionStatus;
use Extensions\Modules\Marketplace\Models\MarketplaceDownload;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Models\MarketplaceResourceVersion;
use Extensions\Modules\Marketplace\Support\MarketplaceNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketplaceResourceVersionActions extends Action
{
    use AuthorizesMarketplaceStaff;

    public const MAX_UPLOAD_KILOBYTES = 102400;

    public function createAsCreator(array $input): MarketplaceResourceVersion
    {
        $validated = Validator::make($input, $this->rules(), [
            'version.regex' => 'Use a semantic version like 1.0.0 or 1.2.3-beta.1.',
        ])->validate();

        $user = $this->user((int) $validated['user_id']);
        $resource = MarketplaceResource::findOrFail($validated['resource_id']);
        $this->assertCanManageResource($user, $resource, TeamRole::Developer);

        $extractPath = $this->normalizeExtractPath(
            $validated['extract_path'] ?? $resource->category?->default_extract_path,
            (bool) ($validated['available_on_integrated_marketplace'] ?? true),
        );

        $stored = $this->storeUpload($validated['file'], $resource);

        $notifyCustomers = (bool) ($validated['notify_customers'] ?? false);
        $status = $resource->status === ResourceStatus::Approved
            ? VersionStatus::Approved
            : VersionStatus::Pending;

        try {
            $version = MarketplaceResourceVersion::create([
                'resource_id' => $resource->id,
                'user_id' => $user->id,
                'name' => $validated['name'],
                'version' => $validated['version'],
                'wemx_version' => $validated['wemx_version'] ?? '*',
                'changelog' => $validated['changelog'] ?? null,
                'available_on_integrated_marketplace' => $validated['available_on_integrated_marketplace'] ?? true,
                'extract_path' => $extractPath,
                'rename_extract_to' => $validated['rename_extract_to'] ?? null,
                'disk' => 'local',
                'path' => $stored['path'],
                'original_name' => $stored['original_name'],
                'mime_type' => $stored['mime_type'],
                'size' => $stored['size'],
                'checksum' => $stored['checksum'],
                'status' => $status,
                'notify_customers' => $notifyCustomers,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($stored['path']);

            throw $exception;
        }

        $version = $version->fresh('resource');

        if ($status === VersionStatus::Pending) {
            MarketplaceNotifier::versionSubmitted($version);
        } elseif ($notifyCustomers) {
            MarketplaceNotifier::versionReleased($version);
        }

        return $version;
    }

    public function deleteAsCreator(array $input): bool
    {
        $validated = Validator::make($input, [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'version_id' => ['required', 'integer', 'exists:marketplace_resource_versions,id'],
        ])->validate();

        $user = $this->user((int) $validated['user_id']);
        $version = MarketplaceResourceVersion::query()->with('resource')->findOrFail($validated['version_id']);
        $resource = $version->resource;

        $this->assertCanManageResource($user, $resource, TeamRole::Developer);

        $versionCount = $resource->versions()->count();

        if ($versionCount <= 1) {
            throw ValidationException::withMessages([
                'version_id' => 'Keep at least one version on the resource.',
            ]);
        }

        self::deleteStoredFile($version);
        $version->delete();

        return true;
    }

    public function approveAsAdmin(array $input): MarketplaceResourceVersion
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'version_id' => ['required', 'integer', 'exists:marketplace_resource_versions,id'],
        ])->validate();

        $this->staffUser((int) $validated['admin_user_id']);

        $version = MarketplaceResourceVersion::findOrFail($validated['version_id']);
        $version->update(['status' => VersionStatus::Approved]);

        return $version->fresh('resource');
    }

    public function rejectAsAdmin(array $input): MarketplaceResourceVersion
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'version_id' => ['required', 'integer', 'exists:marketplace_resource_versions,id'],
        ])->validate();

        $this->staffUser((int) $validated['admin_user_id']);

        $version = MarketplaceResourceVersion::findOrFail($validated['version_id']);
        $version->update(['status' => VersionStatus::Rejected]);

        return $version->fresh('resource');
    }

    public function downloadForUser(array $input): StreamedResponse
    {
        $validated = Validator::make($input, [
            'version_id' => ['required', 'integer', 'exists:marketplace_resource_versions,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'source' => ['sometimes', 'string', 'max:40'],
        ])->validate();

        $version = MarketplaceResourceVersion::query()->with('resource')->findOrFail($validated['version_id']);
        $resource = $version->resource;
        $user = isset($validated['user_id']) ? $this->user((int) $validated['user_id']) : null;

        if (! $resource->isVisibleTo($user) || ! $this->versionIsDownloadable($resource, $version)) {
            throw ValidationException::withMessages([
                'version_id' => 'This version is not available to download.',
            ]);
        }

        $license = null;

        if ($user && $resource->userCan($user, TeamRole::Support)) {
            // Team and staff can always download their own files.
        } elseif (! $resource->isFree()) {
            if (! $user) {
                throw ValidationException::withMessages([
                    'version_id' => 'Sign in to download this paid resource.',
                ]);
            }

            $license = MarketplaceLicense::query()
                ->where('resource_id', $resource->id)
                ->where('user_id', $user->id)
                ->active()
                ->first();

            if (! $license) {
                throw ValidationException::withMessages([
                    'version_id' => 'A valid license is required to download this resource.',
                ]);
            }
        } elseif ($user) {
            $license = MarketplaceLicense::actions()->grantFreeLicense($resource, $user);
        }

        return $this->streamDownload($version, $user, $license, $validated['source'] ?? 'marketplace');
    }

    public function downloadForIntegrated(array $input): StreamedResponse
    {
        $validated = Validator::make($input, [
            'version_id' => ['required', 'integer', 'exists:marketplace_resource_versions,id'],
            'license_key' => ['nullable', 'string', 'max:80'],
        ])->validate();

        $version = MarketplaceResourceVersion::query()->with('resource')->findOrFail($validated['version_id']);
        $resource = $version->resource;

        if (
            $resource->status !== ResourceStatus::Approved
            || ! $resource->available_on_integrated_marketplace
            || ! $this->versionIsDownloadable($resource, $version)
            || ! $version->available_on_integrated_marketplace
        ) {
            throw ValidationException::withMessages([
                'version_id' => 'This version is not available on the integrated marketplace.',
            ]);
        }

        $license = null;

        if (! $resource->isFree()) {
            $license = MarketplaceLicense::query()
                ->where('resource_id', $resource->id)
                ->where('license_key', $validated['license_key'] ?? '')
                ->active()
                ->first();

            if (! $license) {
                throw ValidationException::withMessages([
                    'license_key' => 'A valid license key is required to install this resource.',
                ]);
            }

            $license->update(['last_validated_at' => now()]);
        }

        return $this->streamDownload($version, $license?->user, $license, 'integrated');
    }

    protected function versionIsDownloadable(MarketplaceResource $resource, MarketplaceResourceVersion $version): bool
    {
        if ($version->status === VersionStatus::Rejected) {
            return false;
        }

        if ($resource->status === ResourceStatus::Approved) {
            return true;
        }

        return $version->status === VersionStatus::Approved;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'resource_id' => ['required', 'integer', 'exists:marketplace_resources,id'],
            'name' => ['required', 'string', 'max:120'],
            'version' => ['required', 'string', 'max:40', 'regex:/^\d+\.\d+\.\d+(-[0-9A-Za-z]+(\.[0-9A-Za-z]+)*)?$/'],
            'wemx_version' => ['required', 'string', 'max:40', 'regex:/^(\*|[0-9]+(\.[0-9A-Za-z\-_]+)*)$/'],
            'changelog' => ['nullable', 'string', 'max:20000'],
            'available_on_integrated_marketplace' => ['sometimes', 'boolean'],
            'notify_customers' => ['sometimes', 'boolean'],
            'extract_path' => ['nullable', 'string', 'max:255'],
            'rename_extract_to' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'file' => ['required', 'file', 'max:'.self::MAX_UPLOAD_KILOBYTES, 'mimes:zip'],
        ];
    }

    /**
     * @return array{path: string, original_name: string, mime_type: ?string, size: int, checksum: string}
     */
    protected function storeUpload(UploadedFile $file, MarketplaceResource $resource): array
    {
        $directory = 'marketplace/versions/'.$resource->id;
        $filename = Str::uuid().'.zip';
        $path = $file->storeAs($directory, $filename, 'local');

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    protected function normalizeExtractPath(?string $path, bool $integrated): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            if ($integrated) {
                throw ValidationException::withMessages([
                    'extract_path' => 'Set where the zip should be extracted for one-click installs.',
                ]);
            }

            return null;
        }

        $normalized = str_replace('\\', '/', $path);
        $normalized = ltrim($normalized, '/');

        if (str_contains($normalized, '..') || str_starts_with($normalized, '/')) {
            throw ValidationException::withMessages([
                'extract_path' => 'The extract path must stay inside the WemX application.',
            ]);
        }

        return $normalized;
    }

    protected function streamDownload(
        MarketplaceResourceVersion $version,
        $user,
        ?MarketplaceLicense $license,
        string $source,
    ): StreamedResponse {
        if (! $version->storageDisk()->exists($version->path)) {
            throw ValidationException::withMessages([
                'version_id' => 'The download file is missing.',
            ]);
        }

        MarketplaceDownload::query()->create([
            'resource_id' => $version->resource_id,
            'version_id' => $version->id,
            'user_id' => $user?->id,
            'license_id' => $license?->id,
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 512, ''),
            'source' => $source,
        ]);

        $version->increment('downloads_count');
        $version->resource->increment('downloads_count');

        return $version->storageDisk()->download($version->path, $version->original_name);
    }

    public static function deleteStoredFile(MarketplaceResourceVersion $version): void
    {
        if ($version->path) {
            Storage::disk($version->disk ?: 'local')->delete($version->path);
        }
    }
}
