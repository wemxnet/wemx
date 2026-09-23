<?php

use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Models\MarketplaceCategory;
use Extensions\Modules\Marketplace\Models\MarketplaceCreatorGatewayConfig;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public int $resourceId;

    public string $name = '';

    public string $short_description = '';

    public ?int $category_id = null;

    public string $description = '';

    public bool $available_on_integrated_marketplace = true;

    public string $price = '0';

    public string $website_url = '';

    public string $docs_url = '';

    public string $source_url = '';

    public string $support_url = '';

    public string $license_type = 'proprietary';

    public string $tags = '';

    public ?int $gateway_config_id = null;

    public bool $showPreview = false;

    public function mount(): void
    {
        abort_unless($this->resource->userCan(auth()->user(), TeamRole::Support), 403);
        $this->fillFromResource();
    }

    public function fillFromResource(): void
    {
        $resource = $this->resource;
        $this->name = $resource->name;
        $this->short_description = $resource->short_description;
        $this->category_id = $resource->category_id;
        $this->description = $resource->description;
        $this->available_on_integrated_marketplace = $resource->available_on_integrated_marketplace;
        $this->price = $this->formatPrice($resource->price);
        $this->website_url = (string) $resource->website_url;
        $this->docs_url = (string) $resource->docs_url;
        $this->source_url = (string) $resource->source_url;
        $this->support_url = (string) $resource->support_url;
        $this->license_type = $resource->license_type;
        $this->tags = implode(', ', $resource->tags ?? []);
        $this->gateway_config_id = $resource->gateway_config_id;
    }

    #[Computed]
    public function resource(): MarketplaceResource
    {
        return MarketplaceResource::query()
            ->with(['category', 'gatewayConfig'])
            ->findOrFail($this->resourceId);
    }

    #[Computed]
    public function categories()
    {
        return MarketplaceCategory::query()->visible()->ordered()->get();
    }

    #[Computed]
    public function gateways()
    {
        return MarketplaceCreatorGatewayConfig::query()
            ->where('user_id', $this->resource->user_id)
            ->enabled()
            ->latest()
            ->get();
    }

    public function togglePreview(): void
    {
        $this->showPreview = ! $this->showPreview;
    }

    public function save(): void
    {
        MarketplaceResource::actions()->updateAsCreator([
            'user_id' => auth()->id(),
            'resource_id' => $this->resourceId,
            'category_id' => $this->category_id,
            'gateway_config_id' => $this->gateway_config_id ?: null,
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
        ]);

        unset($this->resource);
        $this->fillFromResource();
        session()->flash('success', 'Resource updated.');
    }

    protected function formatPrice(mixed $price): string
    {
        $formatted = number_format((float) $price, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
}

?>

@php
    $resource = $this->resource;
    $canEdit = $resource->userCan(auth()->user(), TeamRole::Manager);
@endphp

<div>
    @if(session('success'))
        <x-theme::alert.success :text="session('success')" />
    @endif

    <x-theme::card class="mb-4">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Resource</h2>

        @if($canEdit)
            <form wire:submit="save" class="space-y-4">
                <div>
                    <x-theme::form.label for="name" text="Name"/>
                    <x-theme::form.input id="name" wire:model="name"/>
                    @error('name') <x-theme::form.error :text="$message"/> @enderror
                </div>
                <div>
                    <x-theme::form.label for="short_description" text="Short description"/>
                    <x-theme::form.input id="short_description" wire:model="short_description"/>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-theme::form.label for="category_id" text="Category"/>
                        <select id="category_id" wire:model="category_id" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach($this->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-theme::form.label for="price" text="Price"/>
                        <x-theme::form.input id="price" type="number" step="0.01" min="0" wire:model="price"/>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-theme::form.label for="license_type" text="License type"/>
                        <select id="license_type" wire:model="license_type" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach(MarketplaceResource::licenseTypes() as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-theme::form.label for="tags" text="Tags"/>
                        <x-theme::form.input id="tags" wire:model="tags" placeholder="pterodactyl, eggs, game"/>
                    </div>
                </div>
                <div>
                    <x-theme::form.label for="description" text="Description"/>
                    <x-marketplace::markdown-composer id="edit-description" wire:model="description" :showPreview="$showPreview" :previewHtml="\Illuminate\Support\Str::markdown($description, ['html_input' => 'strip', 'allow_unsafe_links' => false])" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-theme::form.input wire:model="website_url" placeholder="Website URL"/>
                    <x-theme::form.input wire:model="docs_url" placeholder="Docs URL"/>
                    <x-theme::form.input wire:model="source_url" placeholder="Source URL"/>
                    <x-theme::form.input wire:model="support_url" placeholder="Support URL"/>
                </div>
                <div>
                    <x-theme::form.label for="gateway_config_id" text="Payment method"/>
                    <select id="gateway_config_id" wire:model="gateway_config_id" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">None</option>
                        @foreach($this->gateways as $gateway)
                            <option value="{{ $gateway->id }}">{{ $gateway->name }} ({{ $gateway->driverName() }})</option>
                        @endforeach
                    </select>
                    <x-theme::form.description text="Paid resources need a payment method from the creator studio."/>
                </div>
                <x-theme::form.toggle wire:model="available_on_integrated_marketplace" text="Available on the integrated marketplace"/>
                <div class="flex justify-end">
                    <x-theme::button.primary type="submit">Save listing</x-theme::button.primary>
                </div>
            </form>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">You can view this listing, but only managers can edit it.</p>
        @endif
    </x-theme::card>
</div>
