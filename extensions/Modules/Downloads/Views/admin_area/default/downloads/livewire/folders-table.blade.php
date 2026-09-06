<?php

use Extensions\Modules\Downloads\Models\DownloadFolder;
use Livewire\Volt\Component;

new class extends Component
{
    public function move(int $folderId, string $direction): void
    {
        DownloadFolder::actions()->moveAsAdmin([
            'admin_user_id' => auth()->id(),
            'folder_id' => $folderId,
            'direction' => $direction,
        ]);
    }
}

?>

@php
    $folders = DownloadFolder::query()->ordered()->withCount('files')->get();
@endphp

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Folders</h3>
    </div>
    @if($folders->isEmpty())
        <div class="card-body">
            <p class="text-secondary mb-0">Create a folder, then upload files customers can download.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Folder</th>
                        <th>Files</th>
                        <th>Status</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($folders as $folder)
                        <tr wire:key="folder-{{ $folder->id }}">
                            <td>
                                <a href="{{ route('admin.downloads.folders.show', $folder) }}" wire:navigate>{{ $folder->name }}</a>
                                <div class="text-secondary">{{ $folder->slug }}</div>
                            </td>
                            <td>{{ $folder->files_count }}</td>
                            <td>
                                @if($folder->is_visible)
                                    <span class="badge bg-green-lt">Visible</span>
                                @else
                                    <span class="badge bg-secondary-lt">Hidden</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    @perm('admin.downloads.update')
                                        <button type="button" class="btn btn-icon btn-outline-secondary" wire:click="move({{ $folder->id }}, 'up')" title="Move up">&uarr;</button>
                                        <button type="button" class="btn btn-icon btn-outline-secondary" wire:click="move({{ $folder->id }}, 'down')" title="Move down">&darr;</button>
                                    @endperm
                                    <a href="{{ route('admin.downloads.folders.show', $folder) }}" class="btn btn-outline-primary" wire:navigate>Open</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
