<?php

use Extensions\Modules\Marketplace\Actions\MarketplaceResourceActions;
use Extensions\Modules\Marketplace\Models\MarketplaceCategory;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Support\MarketplaceLimits;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $short_description = '';

    public ?int $category_id = null;

    public $icon = null;

    public string $description = '';

    public bool $available_on_integrated_marketplace = true;

    public string $price = '0';

    public string $website_url = '';

    public string $docs_url = '';

    public string $source_url = '';

    public string $support_url = '';

    public string $license_type = 'proprietary';

    public string $tags = '';

    public bool $showPreview = false;

    public bool $canCreate = true;

    public ?string $createBlockedMessage = null;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);

        try {
            MarketplaceLimits::assertCanCreateResource(auth()->user());
        } catch (ValidationException $exception) {
            $this->canCreate = false;
            $this->createBlockedMessage = collect($exception->errors())->flatten()->first();
        }

        $first = $this->categories->first();
        $this->category_id = $first?->id;
    }

    #[Computed]
    public function categories()
    {
        return MarketplaceCategory::query()->visible()->ordered()->get();
    }

    public function togglePreview(): void
    {
        $this->showPreview = ! $this->showPreview;
    }

    public function updatedIcon(): void
    {
        if (! $this->icon) {
            return;
        }

        $this->validate(MarketplaceResourceActions::iconRules());
    }

    public function saveResource(): mixed
    {
        if ($this->icon) {
            $this->validate(MarketplaceResourceActions::iconRules());
        }

        $payload = [
            'user_id' => auth()->id(),
            'category_id' => $this->category_id,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'website_url' => $this->website_url ?: null,
            'docs_url' => $this->docs_url ?: null,
            'source_url' => $this->source_url ?: null,
            'support_url' => $this->support_url ?: null,
            'price' => $this->price === '' ? 0 : $this->price,
            'license_type' => $this->license_type,
            'tags' => $this->tags,
            'available_on_integrated_marketplace' => $this->available_on_integrated_marketplace,
        ];

        if ($this->icon) {
            $payload['icon'] = $this->icon;
        }

        $resource = MarketplaceResource::actions()->createAsCreator($payload);

        session()->flash('success', 'Listing saved. Upload the first downloadable version.');

        return $this->redirect($resource->studioUrl('versions'), navigate: true);
    }
}

?>

@php
    $iconPreviewUrl = null;

    if ($icon) {
        try {
            $iconPreviewUrl = $icon->temporaryUrl();
        } catch (\Throwable) {
            $iconPreviewUrl = null;
        }
    }
@endphp

<div>
    @if(! $canCreate)
        <x-theme::alert.warning :text="$createBlockedMessage" class="mb-4" />
    @endif

    <x-theme::card class="mb-4">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Resource</h2>
        <form wire:submit="saveResource" class="space-y-5" @disabled(! $canCreate)>
            <div>
                <x-theme::form.label for="name" text="Name"/>
                <x-theme::form.input id="name" wire:model="name" placeholder="Pterodactyl extra eggs"/>
                @error('name') <x-theme::form.error :text="$message"/> @enderror
            </div>
            <div>
                <x-theme::form.label for="short_description" text="Short description"/>
                <x-theme::form.input id="short_description" wire:model="short_description" placeholder="What this resource adds to WemX"/>
                @error('short_description') <x-theme::form.error :text="$message"/> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-theme::form.label for="category_id" text="Category"/>
                    <select id="category_id" wire:model.live="category_id" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @foreach($this->categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <x-theme::form.error :text="$message"/> @enderror
                </div>
                <div>
                    <x-theme::form.label for="license_type" text="License type"/>
                    <select id="license_type" wire:model="license_type" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @foreach(\Extensions\Modules\Marketplace\Models\MarketplaceResource::licenseTypes() as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <x-marketplace::icon-upload-field
                :previewUrl="$iconPreviewUrl"
                :initials="MarketplaceResource::initialsForName($name)"
                label="Icon (optional)"
            />
            <div>
                <x-theme::form.label for="description" text="Markdown description"/>
                <x-marketplace::markdown-composer
                    id="resource-description"
                    wire:model="description"
                    :showPreview="$showPreview"
                    :previewHtml="\Illuminate\Support\Str::markdown($description, ['html_input' => 'strip', 'allow_unsafe_links' => false])"
                    :rows="12"
                />
                @error('description') <x-theme::form.error :text="$message"/> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-theme::form.label for="price" text="Price (0 = free)"/>
                    <x-theme::form.input id="price" type="number" step="0.01" min="0" wire:model="price"/>
                    @error('price') <x-theme::form.error :text="$message"/> @enderror
                </div>
                <div>
                    <x-theme::form.label for="tags" text="Tags"/>
                    <x-theme::form.input id="tags" wire:model="tags" placeholder="pterodactyl, eggs, game"/>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-theme::form.label for="website_url" text="Website"/>
                    <x-theme::form.input id="website_url" type="url" wire:model="website_url" placeholder="https://"/>
                    @error('website_url') <x-theme::form.error :text="$message"/> @enderror
                </div>
                <div>
                    <x-theme::form.label for="docs_url" text="Docs"/>
                    <x-theme::form.input id="docs_url" type="url" wire:model="docs_url" placeholder="https://"/>
                    @error('docs_url') <x-theme::form.error :text="$message"/> @enderror
                </div>
                <div>
                    <x-theme::form.label for="source_url" text="Source"/>
                    <x-theme::form.input id="source_url" type="url" wire:model="source_url" placeholder="https://github.com/"/>
                    @error('source_url') <x-theme::form.error :text="$message"/> @enderror
                </div>
                <div>
                    <x-theme::form.label for="support_url" text="Support"/>
                    <x-theme::form.input id="support_url" type="url" wire:model="support_url" placeholder="https://"/>
                    @error('support_url') <x-theme::form.error :text="$message"/> @enderror
                </div>
            </div>
            <x-theme::form.toggle wire:model="available_on_integrated_marketplace" text="Available on the integrated marketplace"/>
            <div class="flex justify-end">
                <x-theme::button.primary type="submit" wire:loading.attr="disabled" wire:target="icon, saveResource" :disabled="! $canCreate">Continue to versions</x-theme::button.primary>
            </div>
        </form>
    </x-theme::card>
</div>
