<?php

use Extensions\Modules\Knowledgebase\Models\KnowledgebaseArticle;
use Extensions\Modules\Knowledgebase\Models\KnowledgebaseCategory;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $articleId = null;

    public ?int $category_id = null;

    public string $title = '';

    public string $slug = '';

    public string $excerpt = '';

    public string $content = '';

    public string $tags = '';

    public bool $is_published = true;

    public bool $is_featured = false;

    public bool $hidden_from_guests = false;

    public int $sort_order = 0;

    public function mount(?int $articleId = null): void
    {
        $this->articleId = $articleId;

        if ($articleId) {
            $article = $this->article;
            $this->category_id = $article->category_id;
            $this->title = $article->title;
            $this->slug = $article->slug;
            $this->excerpt = (string) $article->excerpt;
            $this->content = $article->content;
            $this->tags = implode(', ', $article->tags ?? []);
            $this->is_published = $article->is_published;
            $this->is_featured = $article->is_featured;
            $this->hidden_from_guests = $article->hidden_from_guests;
            $this->sort_order = $article->sort_order;
        } else {
            $this->category_id = KnowledgebaseCategory::query()->ordered()->value('id');
            $this->sort_order = $this->category_id
                ? KnowledgebaseArticle::nextSortOrder($this->category_id)
                : 10;
        }
    }

    #[Computed]
    public function article(): ?KnowledgebaseArticle
    {
        return $this->articleId ? KnowledgebaseArticle::findOrFail($this->articleId) : null;
    }

    #[Computed]
    public function categories()
    {
        return KnowledgebaseCategory::query()->ordered()->get();
    }

    public function updatedTitle(): void
    {
        if ($this->title && ($this->slug === '' || $this->slug === Str::slug($this->article?->title ?? ''))) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function save(): mixed
    {
        $payload = [
            'admin_user_id' => auth()->id(),
            'category_id' => $this->category_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt ?: null,
            'content' => $this->content,
            'tags' => $this->tags,
            'is_published' => $this->is_published,
            'is_featured' => $this->is_featured,
            'hidden_from_guests' => $this->hidden_from_guests,
            'sort_order' => $this->sort_order,
        ];

        if ($this->articleId) {
            $article = KnowledgebaseArticle::actions()->updateAsAdmin([
                ...$payload,
                'article_id' => $this->articleId,
            ]);
        } else {
            $article = KnowledgebaseArticle::actions()->createAsAdmin($payload);
        }

        return $this->redirect(route('admin.knowledgebase.articles.edit', $article), navigate: true);
    }

    public function delete(): mixed
    {
        KnowledgebaseArticle::actions()->deleteAsAdmin([
            'admin_user_id' => auth()->id(),
            'article_id' => $this->articleId,
        ]);

        return $this->redirect(route('admin.knowledgebase.index'), navigate: true);
    }
}

?>

<form class="card" wire:submit="save">
    <div class="card-header">
        <h3 class="card-title">{{ $articleId ? 'Edit article' : 'New article' }}</h3>
    </div>
    <div class="card-body">
        @if($this->categories->isEmpty())
            <div class="alert alert-warning">
                Create a category before adding articles.
                <a href="{{ route('admin.knowledgebase.categories.create') }}" wire:navigate>New category</a>
            </div>
        @endif

        <div class="mb-3 row">
            <label class="col-3 col-form-label required">Title</label>
            <div class="col">
                <input type="text" class="form-control @error('title') is-invalid @enderror" wire:model.blur="title" placeholder="How do I reset my password?">
                @error('title') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Slug</label>
            <div class="col">
                <input type="text" class="form-control" wire:model="slug" autocomplete="off">
                <small class="form-hint">Used in the public URL. Leave blank to generate from the title.</small>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label required">Category</label>
            <div class="col">
                <select class="form-select @error('category_id') is-invalid @enderror" wire:model="category_id">
                    @foreach($this->categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Summary</label>
            <div class="col">
                <textarea class="form-control" rows="2" wire:model="excerpt" placeholder="Shown in category lists and search results"></textarea>
                <small class="form-hint">Leave empty to generate from the article body.</small>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label required">Content</label>
            <div class="col">
                <x-admin::form.markdown-editor id="knowledgebase-content" wire:model="content" :rows="16" />
                <small class="form-hint">Markdown is supported. Headings become the table of contents on the article page.</small>
                @error('content') <x-admin::form.error :message="$message"/> @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Tags</label>
            <div class="col">
                <input type="text" class="form-control" wire:model="tags" placeholder="billing, invoices, payments">
                <small class="form-hint">Comma-separated. Used to suggest related articles.</small>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label">Visibility</label>
            <div class="col">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" wire:model="is_published">
                    <span class="form-check-label">Published — visible in the client knowledgebase</span>
                </label>
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" wire:model="is_featured">
                    <span class="form-check-label">Featured — highlight on the knowledgebase home</span>
                </label>
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" wire:model="hidden_from_guests">
                    <span class="form-check-label">Clients only — hide from guests who are not signed in</span>
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
            @if($articleId)
                @perm('admin.knowledgebase.delete')
                    <button type="button" class="btn btn-danger" wire:click="delete" wire:confirm="Delete this article?">Delete</button>
                @endperm
            @endif
        </div>
        <button type="submit" class="btn btn-primary" @disabled($this->categories->isEmpty())>{{ $articleId ? 'Save' : 'Create' }}</button>
    </div>
</form>
