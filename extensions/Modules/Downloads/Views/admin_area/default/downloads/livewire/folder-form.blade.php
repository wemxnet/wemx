<?php

use Extensions\Modules\Downloads\Models\DownloadFolder;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $folderId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public bool $is_visible = true;

    public int $sort_order = 0;

    public function mount(?int $folderId = null): void
    {
        $this->folderId = $folderId;

        if ($folderId) {
            $folder = $this->folder;
            $this->name = $folder->name;
            $this->slug = $folder->slug;
            $this->description = (string) $folder->description;
            $this->is_visible = $folder->is_visible;
            $this->sort_order = $folder->sort_order;
        } else {
            $this->sort_order = DownloadFolder::nextSortOrder();
        }
    }

    #[Computed]
    public function folder(): ?DownloadFolder
    {
        return $this->folderId ? DownloadFolder::findOrFail($this->folderId) : null;
    }

    public function updatedName(): void
    {
        if ($this->name && ($this->slug === '' || $this->slug === Str::slug($this->originalNameHint()))) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function save(): mixed
    {
        $payload = [
            'admin_user_id' => auth()->id(),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?: null,
            'is_visible' => $this->is_visible,
            'sort_order' => $this->sort_order,
        ];

        if ($this->folderId) {
            DownloadFolder::actions()->updateAsAdmin([
                ...$payload,
                'folder_id' => $this->folderId,
            ]);

            return $this->redirect(route('admin.downloads.folders.show', $this->folderId), navigate: true);
        }

        $folder = DownloadFolder::actions()->createAsAdmin($payload);

        return $this->redirect(route('admin.downloads.folders.show', $folder), navigate: true);
    }

    public function delete(): mixed
    {
        DownloadFolder::actions()->deleteAsAdmin([
            'admin_user_id' => auth()->id(),
            'folder_id' => $this->folderId,
        ]);

        return $this->redirect(route('admin.downloads.index'), navigate: true);
    }

    protected function originalNameHint(): string
    {
        return $this->folder?->name ?? '';
    }
}

?>

<form class="card" wire:submit="save">
    <div class="card-header">
        <h3 class="card-title">{{ $folderId ? 'Edit folder' : 'Create folder' }}</h3>
    </div>
    <div class="card-body">
        <div class="mb-3 row">
            <label class="col-3 col-form-label required">Name</label>
            <div class="col">
                <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model.blur="name">
                @error('name') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Slug</label>
            <div class="col">
                <input type="text" class="form-control" wire:model="slug">
                <small class="form-hint">Used in the customer URL, for example /downloads/game-files</small>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Description</label>
            <div class="col">
                <textarea class="form-control" rows="3" wire:model="description"></textarea>
                <small class="form-hint">Shown on the customer downloads page under the folder name.</small>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Visibility</label>
            <div class="col">
                <select class="form-select" wire:model="is_visible">
                    <option value="1">Visible — customers can see this folder</option>
                    <option value="0">Hidden — staff only</option>
                </select>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Sort order</label>
            <div class="col">
                <input type="number" class="form-control" wire:model="sort_order" min="0">
                <small class="form-hint">Lower numbers appear first. You can also reorder folders from the list.</small>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between">
        <div>
            @if($folderId)
                @perm('admin.downloads.delete')
                    <button type="button" class="btn btn-danger" wire:click="delete" wire:confirm="Delete this folder and every file inside it?">Delete</button>
                @endperm
            @endif
        </div>
        <button type="submit" class="btn btn-primary">{{ $folderId ? 'Save' : 'Create' }}</button>
    </div>
</form>
