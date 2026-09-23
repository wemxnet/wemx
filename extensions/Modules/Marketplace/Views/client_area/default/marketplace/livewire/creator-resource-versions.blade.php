<?php

use Extensions\Modules\Marketplace\Enums\ResourceStatus;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Models\MarketplaceResourceVersion;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public int $resourceId;

    public string $version_name = '';

    public string $version_number = '';

    public string $wemx_version = '*';

    public string $changelog = '';

    public bool $version_integrated = true;

    public bool $notify_customers = false;

    public string $extract_path = '';

    public string $rename_extract_to = '';

    public $package = null;

    public function mount(): void
    {
        abort_unless($this->resource->userCan(auth()->user(), TeamRole::Support), 403);
        $this->extract_path = (string) ($this->resource->category?->default_extract_path ?? '');

        if ($this->resource->versions->isEmpty()) {
            $this->version_name = 'Initial release';
            $this->version_number = '1.0.0';
        }
    }

    #[Computed]
    public function resource(): MarketplaceResource
    {
        return MarketplaceResource::query()
            ->with(['category', 'versions'])
            ->findOrFail($this->resourceId);
    }

    public function addVersion(): mixed
    {
        MarketplaceResourceVersion::actions()->createAsCreator([
            'user_id' => auth()->id(),
            'resource_id' => $this->resourceId,
            'name' => $this->version_name,
            'version' => $this->version_number,
            'wemx_version' => $this->wemx_version,
            'changelog' => $this->changelog ?: null,
            'available_on_integrated_marketplace' => $this->version_integrated,
            'notify_customers' => $this->notify_customers,
            'extract_path' => $this->extract_path ?: null,
            'rename_extract_to' => $this->rename_extract_to ?: null,
            'file' => $this->package,
        ]);

        $resource = $this->resource->fresh(['category', 'versions']);

        if ($resource->status === ResourceStatus::Pending && $resource->versions->count() === 1) {
            session()->flash('pending', __('marketplace::messages.pending_approval'));

            return $this->redirect($resource->clientUrl(), navigate: true);
        }

        $this->reset(['version_name', 'version_number', 'changelog', 'package', 'rename_extract_to', 'notify_customers']);
        unset($this->resource);
        session()->flash(
            'success',
            $resource->status === ResourceStatus::Approved
                ? 'Version published.'
                : 'Version uploaded. The listing is still pending approval.'
        );

        return null;
    }

    public function deleteVersion(int $versionId): void
    {
        MarketplaceResourceVersion::actions()->deleteAsCreator([
            'user_id' => auth()->id(),
            'version_id' => $versionId,
        ]);

        unset($this->resource);
        session()->flash('success', 'Version deleted.');
    }
}

?>

@php
    $resource = $this->resource;
    $canVersion = $resource->userCan(auth()->user(), TeamRole::Developer);
    $versions = $resource->versions;
    $canDelete = $versions->count() > 1;
@endphp

<div>
    @if(session('success'))
        <x-theme::alert.success :text="session('success')" />
    @endif

    <x-theme::card class="mb-4">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Versions</h2>

        @if($versions->isEmpty())
            <x-theme::alert.primary text="Upload the first downloadable version. The listing stays pending until an administrator reviews it." />
        @else
            <div class="space-y-3">
                @foreach($versions as $version)
                    <div wire:key="ver-{{ $version->id }}" class="rounded-lg border border-gray-100 p-3 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <strong class="text-gray-900 dark:text-white">{{ $version->name }} v{{ $version->version }}</strong>
                            </div>
                            @if($canVersion && $canDelete)
                                <button
                                    type="button"
                                    class="text-sm text-red-600 hover:underline dark:text-red-400"
                                    wire:click="deleteVersion({{ $version->id }})"
                                    wire:confirm="Delete this version permanently?"
                                >Delete</button>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $version->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                            · {{ $version->humanSize() }}
                            · WemX {{ $version->wemx_version }}
                            @if($version->extract_path) · {{ $version->extract_path }}@endif
                        </p>
                        @if($version->changelog)
                            <div class="prose prose-sm mt-2 max-w-none text-gray-700 dark:prose-invert dark:text-gray-200 [&_*]:text-inherit">{!! $version->renderedChangelog() !!}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if($canVersion)
            <form wire:submit="addVersion" class="mt-6 space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                <h3 class="font-medium text-gray-900 dark:text-white">{{ $versions->isEmpty() ? 'Initial version' : 'Upload a version' }}</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <x-theme::form.label for="version_name" text="Version name"/>
                        <x-theme::form.input id="version_name" wire:model="version_name" placeholder="Version name"/>
                    </div>
                    <div>
                        <x-theme::form.label for="version_number" text="Version number"/>
                        <x-theme::form.input id="version_number" wire:model="version_number" placeholder="1.0.0"/>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Use semantic versions like <code>1.0.0</code> or <code>1.2.3-beta.1</code>.</p>
                        @error('version') <x-theme::form.error :text="$message"/> @enderror
                    </div>
                </div>
                <div>
                    <x-theme::form.label for="wemx_version" text="Supported WemX version"/>
                    <x-theme::form.input id="wemx_version" wire:model="wemx_version" placeholder="Supported WemX version"/>
                </div>
                <div>
                    <x-theme::form.label for="changelog" text="Changelog"/>
                    <x-theme::form.textarea id="changelog" wire:model="changelog" rows="4" placeholder="Changelog"/>
                </div>
                <div>
                    <x-theme::form.label for="package" text="Zip file"/>
                    <x-theme::form.file id="package" wire:model="package" accept=".zip,application/zip"/>
                    <div wire:loading wire:target="package" class="mt-2 text-xs text-gray-500 dark:text-gray-400">Uploading…</div>
                </div>
                @error('file') <x-theme::form.error :text="$message"/> @enderror
                <x-theme::form.toggle wire:model.live="version_integrated" text="Downloadable from the integrated marketplace"/>
                @if($version_integrated)
                    <x-theme::form.input wire:model="extract_path" placeholder="Extract path"/>
                    <x-theme::form.input wire:model="rename_extract_to" placeholder="Rename extracted folder"/>
                @endif
                @if($resource->status === ResourceStatus::Approved)
                    <x-theme::form.toggle wire:model="notify_customers" text="Email customers about this release"/>
                @endif
                <x-theme::button.primary type="submit">{{ $versions->isEmpty() ? 'Publish for review' : 'Publish version' }}</x-theme::button.primary>
            </form>
        @endif
    </x-theme::card>
</div>
