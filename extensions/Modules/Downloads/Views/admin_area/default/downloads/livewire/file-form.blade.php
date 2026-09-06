<?php

use App\Models\Package;
use Extensions\Modules\Downloads\Models\DownloadFile;
use Extensions\Modules\Downloads\Models\DownloadFolder;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?int $fileId = null;

    public int $folderId;

    public string $name = '';

    public string $description = '';

    public string $version = '';

    public $upload = null;

    public bool $is_published = true;

    public bool $allow_guests = false;

    public bool $require_any_order = false;

    public bool $require_active_order = true;

    public bool $hidden_until_eligible = false;

    public array $package_ids = [];

    public ?int $download_limit = null;

    public ?string $available_from = null;

    public ?string $available_until = null;

    public int $sort_order = 0;

    public function mount(?int $fileId = null, ?int $folderId = null): void
    {
        $this->fileId = $fileId;

        if ($fileId) {
            $file = $this->file;
            $this->folderId = $file->folder_id;
            $this->name = $file->name;
            $this->description = (string) $file->description;
            $this->version = (string) $file->version;
            $this->is_published = $file->is_published;
            $this->allow_guests = $file->allow_guests;
            $this->require_any_order = $file->require_any_order;
            $this->require_active_order = $file->require_active_order;
            $this->hidden_until_eligible = $file->hidden_until_eligible;
            $this->package_ids = array_map('strval', $file->requiredPackageIds());
            $this->download_limit = $file->download_limit;
            $this->available_from = $file->available_from?->format('Y-m-d\TH:i');
            $this->available_until = $file->available_until?->format('Y-m-d\TH:i');
            $this->sort_order = $file->sort_order;
        } else {
            $this->folderId = $folderId ?? 0;
            $this->sort_order = DownloadFile::nextSortOrder($this->folderId);
        }
    }

    #[Computed]
    public function file(): ?DownloadFile
    {
        return $this->fileId ? DownloadFile::findOrFail($this->fileId) : null;
    }

    #[Computed]
    public function folder(): DownloadFolder
    {
        return DownloadFolder::findOrFail($this->folderId);
    }

    #[Computed]
    public function packages()
    {
        return Package::query()->orderBy('name')->get(['id', 'name']);
    }

    public function updatedUpload(): void
    {
        if ($this->name === '' && $this->upload) {
            $this->name = pathinfo($this->upload->getClientOriginalName(), PATHINFO_FILENAME);
        }
    }

    public function updatedAllowGuests(): void
    {
        if ($this->allow_guests) {
            $this->require_any_order = false;
            $this->package_ids = [];
        }
    }

    public function save(): mixed
    {
        $payload = [
            'admin_user_id' => auth()->id(),
            'folder_id' => $this->folderId,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'version' => $this->version ?: null,
            'is_published' => $this->is_published,
            'allow_guests' => $this->allow_guests,
            'require_any_order' => $this->require_any_order,
            'require_active_order' => $this->require_active_order,
            'hidden_until_eligible' => $this->hidden_until_eligible,
            'package_ids' => array_map('intval', $this->package_ids),
            'download_limit' => $this->download_limit ?: null,
            'available_from' => $this->available_from ?: null,
            'available_until' => $this->available_until ?: null,
            'sort_order' => $this->sort_order,
        ];

        if ($this->upload) {
            $payload['file'] = $this->upload;
        }

        if ($this->fileId) {
            DownloadFile::actions()->updateAsAdmin([
                ...$payload,
                'file_id' => $this->fileId,
            ]);
        } else {
            DownloadFile::actions()->createAsAdmin($payload);
        }

        return $this->redirect(route('admin.downloads.folders.show', $this->folderId), navigate: true);
    }

    public function delete(): mixed
    {
        DownloadFile::actions()->deleteAsAdmin([
            'admin_user_id' => auth()->id(),
            'file_id' => $this->fileId,
        ]);

        return $this->redirect(route('admin.downloads.folders.show', $this->folderId), navigate: true);
    }
}

?>

<form class="card" wire:submit="save">
    <div class="card-header">
        <h3 class="card-title">{{ $fileId ? 'Edit file' : 'Upload file' }}</h3>
        <div class="card-actions">
            <a href="{{ route('admin.downloads.folders.show', $folderId) }}" class="btn btn-outline-secondary" wire:navigate>{{ $this->folder->name }}</a>
        </div>
    </div>
    <div class="card-body">
        <div class="mb-3 row">
            <label class="col-3 col-form-label {{ $fileId ? '' : 'required' }}">File</label>
            <div class="col">
                <input type="file" class="form-control @error('file') is-invalid @enderror" wire:model="upload">
                <div wire:loading wire:target="upload" class="form-hint">Uploading…</div>
                @if($this->file)
                    <small class="form-hint">Current file: {{ $this->file->original_name }} ({{ $this->file->formattedSize() }}). Leave empty to keep it.</small>
                @else
                    <small class="form-hint">Maximum size 50 MB. Any file type is allowed.</small>
                @endif
                @error('file') <x-admin::form.error :message="$message"/> @enderror
                @error('upload') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label required">Name</label>
            <div class="col">
                <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name">
                @error('name') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Description</label>
            <div class="col">
                <textarea class="form-control" rows="4" wire:model="description" placeholder="What this file is and when to use it"></textarea>
                <small class="form-hint">Markdown is supported. Shown to customers on the folder page.</small>
                @error('description') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Version</label>
            <div class="col">
                <input type="text" class="form-control" wire:model="version" placeholder="1.2.0">
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Status</label>
            <div class="col">
                <select class="form-select" wire:model="is_published">
                    <option value="1">Published — listed for customers</option>
                    <option value="0">Draft — hidden from customers</option>
                </select>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Who can download</label>
            <div class="col">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" wire:model.live="allow_guests">
                    <span class="form-check-label">Guests can download this file without signing in</span>
                </label>
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" wire:model.live="require_any_order" @disabled($allow_guests)>
                    <span class="form-check-label">Customer must have a service (any package)</span>
                </label>
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" wire:model="require_active_order">
                    <span class="form-check-label">Only count active services (ignore pending or suspended)</span>
                </label>
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" wire:model="hidden_until_eligible">
                    <span class="form-check-label">Hide this file from people who cannot download it</span>
                </label>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Required packages</label>
            <div class="col">
                @if($this->packages->isEmpty())
                    <small class="form-hint">No packages yet. Leave this empty to allow any signed-in customer.</small>
                @else
                    <div class="border rounded p-2" style="max-height: 220px; overflow: auto;">
                        @foreach($this->packages as $package)
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" value="{{ $package->id }}" wire:model.live="package_ids" @disabled($allow_guests)>
                                <span class="form-check-label">{{ $package->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <small class="form-hint">If any packages are selected, the customer must have a matching service. Leave empty for no package requirement.</small>
                @endif
                @error('package_ids') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Download limit</label>
            <div class="col">
                <input type="number" class="form-control" wire:model="download_limit" min="1" placeholder="Unlimited">
                <small class="form-hint">Maximum downloads per customer (or per guest IP). Leave empty for no limit.</small>
                @error('download_limit') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Available from</label>
            <div class="col">
                <input type="datetime-local" class="form-control" wire:model="available_from">
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Available until</label>
            <div class="col">
                <input type="datetime-local" class="form-control" wire:model="available_until">
                <small class="form-hint">Leave both empty to keep the file available indefinitely.</small>
                @error('available_until') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Sort order</label>
            <div class="col">
                <input type="number" class="form-control" wire:model="sort_order" min="0">
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between">
        <div>
            @if($fileId)
                @perm('admin.downloads.delete')
                    <button type="button" class="btn btn-danger" wire:click="delete" wire:confirm="Delete this file?">Delete</button>
                @endperm
            @endif
        </div>
        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ $fileId ? 'Save' : 'Upload' }}</button>
    </div>
</form>
