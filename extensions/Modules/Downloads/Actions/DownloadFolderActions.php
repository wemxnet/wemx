<?php

namespace Extensions\Modules\Downloads\Actions;

use App\Actions\Action;
use App\Models\User;
use Extensions\Modules\Downloads\Models\DownloadFolder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DownloadFolderActions extends Action
{
    public function createAsAdmin(array $input): DownloadFolder
    {
        $validated = Validator::make($input, $this->rules())->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.downloads.create');
        unset($validated['admin_user_id']);

        $validated['slug'] = DownloadFolder::generateSlug($validated['slug'] ?? $validated['name']);
        $validated['sort_order'] ??= DownloadFolder::nextSortOrder();

        return DownloadFolder::create(self::omitNullValues($validated));
    }

    public function updateAsAdmin(array $input): DownloadFolder
    {
        $validated = Validator::make($input, array_merge($this->rules(updating: true), [
            'folder_id' => ['required', 'integer', 'exists:download_folders,id'],
        ]))->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.downloads.update');

        $folder = DownloadFolder::findOrFail($validated['folder_id']);

        unset($validated['admin_user_id'], $validated['folder_id']);

        if (isset($validated['name']) || isset($validated['slug'])) {
            $validated['slug'] = DownloadFolder::generateSlug(
                $validated['slug'] ?? $validated['name'] ?? $folder->name,
                $folder->id,
            );
        }

        $folder->update(self::omitNullValues($validated));

        return $folder->fresh();
    }

    public function deleteAsAdmin(array $input): bool
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'folder_id' => ['required', 'integer', 'exists:download_folders,id'],
        ])->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.downloads.delete');

        $folder = DownloadFolder::query()->with('files')->findOrFail($validated['folder_id']);

        foreach ($folder->files as $file) {
            DownloadFileActions::deleteStoredFile($file);
        }

        return (bool) $folder->delete();
    }

    public function moveAsAdmin(array $input): DownloadFolder
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'folder_id' => ['required', 'integer', 'exists:download_folders,id'],
            'direction' => ['required', 'in:up,down'],
        ])->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.downloads.update');

        $folder = DownloadFolder::findOrFail($validated['folder_id']);
        $ordered = DownloadFolder::query()->ordered()->get();
        $index = $ordered->search(fn (DownloadFolder $item) => $item->id === $folder->id);

        if ($index === false) {
            return $folder;
        }

        $swapWith = $validated['direction'] === 'up'
            ? $ordered->get($index - 1)
            : $ordered->get($index + 1);

        if (! $swapWith) {
            return $folder;
        }

        $currentOrder = $folder->sort_order;
        $folder->update(['sort_order' => $swapWith->sort_order]);
        $swapWith->update(['sort_order' => $currentOrder]);

        if ($folder->sort_order === $swapWith->sort_order) {
            $folder->update(['sort_order' => $validated['direction'] === 'up' ? $swapWith->sort_order - 1 : $swapWith->sort_order + 1]);
        }

        return $folder->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_visible' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    protected function staffUser(int $userId, string $permission): User
    {
        $user = User::find($userId);

        if (! $user || ! $user->isStaff() || ! $user->hasPermission($permission)) {
            throw ValidationException::withMessages([
                'admin_user_id' => 'You do not have permission to manage download folders.',
            ]);
        }

        return $user;
    }
}
