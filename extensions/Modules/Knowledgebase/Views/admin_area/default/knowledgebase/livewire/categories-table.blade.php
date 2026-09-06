<?php

use Extensions\Modules\Knowledgebase\Models\KnowledgebaseCategory;
use Livewire\Volt\Component;

new class extends Component
{
    public function move(int $categoryId, string $direction): void
    {
        KnowledgebaseCategory::actions()->moveAsAdmin([
            'admin_user_id' => auth()->id(),
            'category_id' => $categoryId,
            'direction' => $direction,
        ]);
    }
}

?>

@php
    $categories = KnowledgebaseCategory::treeForAdmin();
@endphp

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Categories</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Articles</th>
                    <th>Visibility</th>
                    <th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                    <tr wire:key="category-{{ $category->id }}">
                        <td>
                            <a href="{{ route('admin.knowledgebase.categories.edit', $category) }}" wire:navigate>{{ $category->name }}</a>
                            <div class="text-secondary">{{ $category->slug }}</div>
                        </td>
                        <td>{{ $category->articles_count }}</td>
                        <td>
                            @if($category->is_visible)
                                <span class="badge bg-green-lt">Visible</span>
                            @else
                                <span class="badge bg-secondary-lt">Hidden</span>
                            @endif
                            @if($category->hidden_from_guests)
                                <span class="badge bg-orange-lt">Clients only</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @perm('admin.knowledgebase.update')
                                <button type="button" class="btn btn-sm" wire:click="move({{ $category->id }}, 'up')">Up</button>
                                <button type="button" class="btn btn-sm" wire:click="move({{ $category->id }}, 'down')">Down</button>
                                <a href="{{ route('admin.knowledgebase.categories.edit', $category) }}" class="ms-2" wire:navigate>Edit</a>
                            @endperm
                        </td>
                    </tr>
                    @foreach($category->children as $child)
                        <tr wire:key="category-{{ $child->id }}">
                            <td class="ps-5">
                                <a href="{{ route('admin.knowledgebase.categories.edit', $child) }}" wire:navigate>{{ $child->name }}</a>
                                <div class="text-secondary">{{ $child->slug }}</div>
                            </td>
                            <td>{{ $child->articles_count }}</td>
                            <td>
                                @if($child->is_visible)
                                    <span class="badge bg-green-lt">Visible</span>
                                @else
                                    <span class="badge bg-secondary-lt">Hidden</span>
                                @endif
                                @if($child->hidden_from_guests)
                                    <span class="badge bg-orange-lt">Clients only</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @perm('admin.knowledgebase.update')
                                    <button type="button" class="btn btn-sm" wire:click="move({{ $child->id }}, 'up')">Up</button>
                                    <button type="button" class="btn btn-sm" wire:click="move({{ $child->id }}, 'down')">Down</button>
                                    <a href="{{ route('admin.knowledgebase.categories.edit', $child) }}" class="ms-2" wire:navigate>Edit</a>
                                @endperm
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="4" class="text-secondary">No categories yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
