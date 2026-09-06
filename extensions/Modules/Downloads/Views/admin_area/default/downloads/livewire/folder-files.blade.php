<?php

use Extensions\Modules\Downloads\Models\DownloadFile;
use Extensions\Modules\Downloads\Models\DownloadFolder;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public int $folderId;

    #[Computed]
    public function folder(): DownloadFolder
    {
        return DownloadFolder::findOrFail($this->folderId);
    }

    public function move(int $fileId, string $direction): void
    {
        DownloadFile::actions()->moveAsAdmin([
            'admin_user_id' => auth()->id(),
            'file_id' => $fileId,
            'direction' => $direction,
        ]);
    }

    public function delete(int $fileId): void
    {
        DownloadFile::actions()->deleteAsAdmin([
            'admin_user_id' => auth()->id(),
            'file_id' => $fileId,
        ]);
    }
}

?>

@php
    $folder = $this->folder;
    $files = $folder->files()->ordered()->get();
@endphp

<div class="space-y-3">
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <div class="text-secondary">Folder</div>
                    <h3 class="mb-1">{{ $folder->name }}</h3>
                    @if($folder->description)
                        <p class="text-secondary mb-0">{{ $folder->description }}</p>
                    @endif
                </div>
                <div>
                    @if($folder->is_visible)
                        <span class="badge bg-green-lt">Visible</span>
                    @else
                        <span class="badge bg-secondary-lt">Hidden</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Files</h3>
        </div>
        @if($files->isEmpty())
            <div class="card-body">
                <p class="text-secondary mb-0">No files in this folder yet.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Access</th>
                            <th>Downloads</th>
                            <th>Status</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($files as $file)
                            <tr wire:key="file-{{ $file->id }}">
                                <td>
                                    <div class="fw-medium">{{ $file->name }}</div>
                                    <div class="text-secondary">
                                        {{ $file->original_name }} · {{ $file->formattedSize() }}
                                        @if($file->version)
                                            · v{{ $file->version }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="text-secondary">{{ $file->accessSummary() }}</div>
                                </td>
                                <td>{{ $file->download_count }}</td>
                                <td>
                                    @if($file->is_published)
                                        <span class="badge bg-green-lt">Published</span>
                                    @else
                                        <span class="badge bg-secondary-lt">Draft</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-list flex-nowrap">
                                        @perm('admin.downloads.update')
                                            <button type="button" class="btn btn-icon btn-outline-secondary" wire:click="move({{ $file->id }}, 'up')" title="Move up">&uarr;</button>
                                            <button type="button" class="btn btn-icon btn-outline-secondary" wire:click="move({{ $file->id }}, 'down')" title="Move down">&darr;</button>
                                            <a href="{{ route('admin.downloads.files.edit', $file) }}" class="btn btn-outline-primary" wire:navigate>Edit</a>
                                        @endperm
                                        @perm('admin.downloads.delete')
                                            <button type="button" class="btn btn-outline-danger" wire:click="delete({{ $file->id }})" wire:confirm="Delete this file?">Delete</button>
                                        @endperm
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
