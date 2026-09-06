<?php

use Extensions\Modules\Knowledgebase\Models\KnowledgebaseCategory;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $categoryId = null;

    public ?int $parent_id = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $icon = 'book';

    public bool $is_visible = true;

    public bool $hidden_from_guests = false;

    public int $sort_order = 0;

    public function mount(?int $categoryId = null): void
    {
        $this->categoryId = $categoryId;

        if ($categoryId) {
            $category = $this->category;
            $this->parent_id = $category->parent_id;
            $this->name = $category->name;
            $this->slug = $category->slug;
            $this->description = (string) $category->description;
            $this->icon = $category->icon ?: 'book';
            $this->is_visible = $category->is_visible;
            $this->hidden_from_guests = $category->hidden_from_guests;
            $this->sort_order = $category->sort_order;
        } else {
            $this->sort_order = KnowledgebaseCategory::nextSortOrder();
        }
    }

    #[Computed]
    public function category(): ?KnowledgebaseCategory
    {
        return $this->categoryId ? KnowledgebaseCategory::findOrFail($this->categoryId) : null;
    }

    #[Computed]
    public function parents()
    {
        return KnowledgebaseCategory::optionsForSelect($this->categoryId);
    }

    public function updatedName(): void
    {
        if ($this->name && ($this->slug === '' || $this->slug === Str::slug($this->category?->name ?? ''))) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function save(): mixed
    {
        $payload = [
            'admin_user_id' => auth()->id(),
            'parent_id' => $this->parent_id ?: null,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?: null,
            'icon' => $this->icon ?: null,
            'is_visible' => $this->is_visible,
            'hidden_from_guests' => $this->hidden_from_guests,
            'sort_order' => $this->sort_order,
        ];

        if ($this->categoryId) {
            KnowledgebaseCategory::actions()->updateAsAdmin([
                ...$payload,
                'category_id' => $this->categoryId,
            ]);
        } else {
            KnowledgebaseCategory::actions()->createAsAdmin($payload);
        }

        return $this->redirect(route('admin.knowledgebase.categories.index'), navigate: true);
    }

    public function delete(): mixed
    {
        KnowledgebaseCategory::actions()->deleteAsAdmin([
            'admin_user_id' => auth()->id(),
            'category_id' => $this->categoryId,
        ]);

        return $this->redirect(route('admin.knowledgebase.categories.index'), navigate: true);
    }
}

?>

<form class="card" wire:submit="save">
    <div class="card-header">
        <h3 class="card-title">{{ $categoryId ? 'Edit category' : 'New category' }}</h3>
    </div>
    <div class="card-body">
        <div class="mb-3 row">
            <label class="col-3 col-form-label required">Name</label>
            <div class="col">
                <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model.blur="name" placeholder="Billing">
                @error('name') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Slug</label>
            <div class="col">
                <input type="text" class="form-control" wire:model="slug" autocomplete="off">
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Parent</label>
            <div class="col">
                <select class="form-select @error('parent_id') is-invalid @enderror" wire:model="parent_id">
                    <option value="">Top level</option>
                    @foreach($this->parents as $parent)
                        <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                    @endforeach
                </select>
                <small class="form-hint">Optional. Categories nest one level deep.</small>
                @error('parent_id') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Description</label>
            <div class="col">
                <textarea class="form-control" rows="2" wire:model="description"></textarea>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Icon</label>
            <div class="col">
                <select class="form-select" wire:model="icon">
                    @foreach(\Extensions\Modules\Knowledgebase\Models\KnowledgebaseCategory::icons() as $icon)
                        <option value="{{ $icon }}">{{ ucfirst(str_replace('-', ' ', $icon)) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Visibility</label>
            <div class="col">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" wire:model="is_visible">
                    <span class="form-check-label">Visible in the client knowledgebase</span>
                </label>
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" wire:model="hidden_from_guests">
                    <span class="form-check-label">Clients only — hide this category from guests</span>
                </label>
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
            @if($categoryId)
                @perm('admin.knowledgebase.delete')
                    <button type="button" class="btn btn-danger" wire:click="delete" wire:confirm="Delete this category? It must be empty.">Delete</button>
                @endperm
            @endif
        </div>
        <button type="submit" class="btn btn-primary">{{ $categoryId ? 'Save' : 'Create' }}</button>
    </div>
</form>
