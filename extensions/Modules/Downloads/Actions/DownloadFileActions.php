<?php

namespace Extensions\Modules\Downloads\Actions;

use App\Actions\Action;
use App\Models\Package;
use App\Models\User;
use Extensions\Modules\Downloads\Models\DownloadFile;
use Extensions\Modules\Downloads\Models\DownloadFolder;
use Extensions\Modules\Downloads\Models\DownloadLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadFileActions extends Action
{
    public const MAX_UPLOAD_KILOBYTES = 51200;

    public function createAsAdmin(array $input): DownloadFile
    {
        $validated = Validator::make($input, $this->rules())->validate();

        $admin = $this->staffUser($validated['admin_user_id'], 'admin.downloads.create');
        $folder = DownloadFolder::findOrFail($validated['folder_id']);
        $uploaded = $validated['file'];

        $this->assertPackageIds($validated['package_ids'] ?? []);

        $stored = $this->storeUpload($uploaded, $folder);

        try {
            return DownloadFile::create([
                'folder_id' => $folder->id,
                'uploaded_by' => $admin->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'version' => $validated['version'] ?? null,
                'disk' => 'local',
                'path' => $stored['path'],
                'original_name' => $stored['original_name'],
                'mime_type' => $stored['mime_type'],
                'size' => $stored['size'],
                'is_published' => $validated['is_published'] ?? true,
                'allow_guests' => $validated['allow_guests'] ?? false,
                'require_any_order' => $validated['require_any_order'] ?? false,
                'require_active_order' => $validated['require_active_order'] ?? true,
                'hidden_until_eligible' => $validated['hidden_until_eligible'] ?? false,
                'package_ids' => $validated['package_ids'] ?? [],
                'download_limit' => $validated['download_limit'] ?? null,
                'available_from' => $validated['available_from'] ?? null,
                'available_until' => $validated['available_until'] ?? null,
                'sort_order' => $validated['sort_order'] ?? DownloadFile::nextSortOrder($folder->id),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($stored['path']);

            throw $exception;
        }
    }

    public function updateAsAdmin(array $input): DownloadFile
    {
        $validated = Validator::make($input, array_merge($this->rules(updating: true), [
            'file_id' => ['required', 'integer', 'exists:download_files,id'],
        ]))->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.downloads.update');

        $file = DownloadFile::findOrFail($validated['file_id']);

        if (array_key_exists('package_ids', $validated)) {
            $this->assertPackageIds($validated['package_ids'] ?? []);
        }

        $stored = null;

        if (isset($validated['file'])) {
            $folder = isset($validated['folder_id'])
                ? DownloadFolder::findOrFail($validated['folder_id'])
                : $file->folder;
            $stored = $this->storeUpload($validated['file'], $folder);
        }

        $payload = [];

        foreach ([
            'folder_id',
            'name',
            'description',
            'version',
            'is_published',
            'allow_guests',
            'require_any_order',
            'require_active_order',
            'hidden_until_eligible',
            'package_ids',
            'download_limit',
            'available_from',
            'available_until',
            'sort_order',
        ] as $key) {
            if (array_key_exists($key, $validated)) {
                $payload[$key] = $validated[$key];
            }
        }

        if ($stored) {
            $previousPath = $file->path;
            $previousDisk = $file->disk;
            $payload['disk'] = 'local';
            $payload['path'] = $stored['path'];
            $payload['original_name'] = $stored['original_name'];
            $payload['mime_type'] = $stored['mime_type'];
            $payload['size'] = $stored['size'];
        }

        try {
            $file->update($payload);
        } catch (\Throwable $exception) {
            if ($stored) {
                Storage::disk('local')->delete($stored['path']);
            }

            throw $exception;
        }

        if ($stored && $previousPath && $previousPath !== $stored['path']) {
            Storage::disk($previousDisk ?: 'local')->delete($previousPath);
        }

        return $file->fresh();
    }

    public function deleteAsAdmin(array $input): bool
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'file_id' => ['required', 'integer', 'exists:download_files,id'],
        ])->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.downloads.delete');

        $file = DownloadFile::findOrFail($validated['file_id']);
        self::deleteStoredFile($file);

        return (bool) $file->delete();
    }

    public function moveAsAdmin(array $input): DownloadFile
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'file_id' => ['required', 'integer', 'exists:download_files,id'],
            'direction' => ['required', 'in:up,down'],
        ])->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.downloads.update');

        $file = DownloadFile::findOrFail($validated['file_id']);
        $ordered = DownloadFile::query()->where('folder_id', $file->folder_id)->ordered()->get();
        $index = $ordered->search(fn (DownloadFile $item) => $item->id === $file->id);

        if ($index === false) {
            return $file;
        }

        $swapWith = $validated['direction'] === 'up'
            ? $ordered->get($index - 1)
            : $ordered->get($index + 1);

        if (! $swapWith) {
            return $file;
        }

        $currentOrder = $file->sort_order;
        $file->update(['sort_order' => $swapWith->sort_order]);
        $swapWith->update(['sort_order' => $currentOrder]);

        if ($file->sort_order === $swapWith->sort_order) {
            $file->update(['sort_order' => $validated['direction'] === 'up' ? $swapWith->sort_order - 1 : $swapWith->sort_order + 1]);
        }

        return $file->fresh();
    }

    public function recordDownload(DownloadFile $file, ?User $user, ?string $ip = null, ?string $userAgent = null): DownloadLog
    {
        $log = $file->logs()->create([
            'user_id' => $user?->id,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? Str::limit($userAgent, 512, '') : null,
        ]);

        $file->increment('download_count');

        return $log;
    }

    public function streamFor(?User $user, DownloadFile $file, ?string $ip = null, ?string $userAgent = null): StreamedResponse
    {
        if (! $file->canBeDownloadedBy($user, $ip)) {
            throw ValidationException::withMessages([
                'file_id' => $file->denialLabel($user, $ip) ?: 'You cannot download this file.',
            ]);
        }

        if (! Storage::disk($file->disk)->exists($file->path)) {
            throw ValidationException::withMessages([
                'file_id' => 'This file is no longer available.',
            ]);
        }

        $this->recordDownload($file, $user, $ip, $userAgent);

        return Storage::disk($file->disk)->download($file->path, $file->downloadName(), [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
        ]);
    }

    /**
     * @return array{path: string, original_name: string, mime_type: string, size: int}
     */
    protected function storeUpload(UploadedFile $uploaded, DownloadFolder $folder): array
    {
        $extension = strtolower((string) $uploaded->getClientOriginalExtension());
        $filename = (string) Str::uuid();

        if ($extension !== '') {
            $filename .= '.'.$extension;
        }

        $path = $uploaded->storeAs('downloads/'.$folder->id, $filename, 'local');

        if (! $path) {
            throw ValidationException::withMessages([
                'file' => 'The file could not be stored.',
            ]);
        }

        return [
            'path' => $path,
            'original_name' => $uploaded->getClientOriginalName(),
            'mime_type' => $uploaded->getMimeType() ?: $uploaded->getClientMimeType(),
            'size' => $uploaded->getSize() ?: 0,
        ];
    }

    public static function deleteStoredFile(DownloadFile $file): void
    {
        if ($file->path) {
            Storage::disk($file->disk ?: 'local')->delete($file->path);
        }
    }

    /**
     * @param  list<int|string>|null  $packageIds
     */
    protected function assertPackageIds(?array $packageIds): void
    {
        $ids = collect($packageIds ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $found = Package::query()->whereIn('id', $ids)->pluck('id');

        if ($found->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'package_ids' => 'One or more selected packages could not be found.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'folder_id' => [$updating ? 'sometimes' : 'required', 'integer', 'exists:download_folders,id'],
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'version' => ['nullable', 'string', 'max:50'],
            'file' => [$updating ? 'nullable' : 'required', 'file', 'max:'.self::MAX_UPLOAD_KILOBYTES],
            'is_published' => ['sometimes', 'boolean'],
            'allow_guests' => ['sometimes', 'boolean'],
            'require_any_order' => ['sometimes', 'boolean'],
            'require_active_order' => ['sometimes', 'boolean'],
            'hidden_until_eligible' => ['sometimes', 'boolean'],
            'package_ids' => ['nullable', 'array'],
            'package_ids.*' => ['integer', Rule::exists('packages', 'id')],
            'download_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after_or_equal:available_from'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }

    protected function staffUser(int $userId, string $permission): User
    {
        $user = User::find($userId);

        if (! $user || ! $user->isStaff() || ! $user->hasPermission($permission)) {
            throw ValidationException::withMessages([
                'admin_user_id' => 'You do not have permission to manage downloads.',
            ]);
        }

        return $user;
    }
}
