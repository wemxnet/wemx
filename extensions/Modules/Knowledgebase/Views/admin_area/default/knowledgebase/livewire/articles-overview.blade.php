<?php

use Extensions\Modules\Knowledgebase\Models\KnowledgebaseArticle;
use Extensions\Modules\Knowledgebase\Models\KnowledgebaseCategory;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    #[Url]
    public ?int $category_id = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryId(): void
    {
        $this->resetPage();
    }
}

?>

@php
    $base = KnowledgebaseArticle::query();
    $publishedCount = (clone $base)->published()->count();
    $draftCount = (clone $base)->where('is_published', false)->count();
    $totalViews = (int) (clone $base)->sum('views_count');
    $helpful = (int) (clone $base)->sum('helpful_count');
    $unhelpful = (int) (clone $base)->sum('unhelpful_count');
    $helpfulPercent = ($helpful + $unhelpful) > 0 ? (int) round(($helpful / ($helpful + $unhelpful)) * 100) : 0;

    $query = KnowledgebaseArticle::query()->with('category')->latest('updated_at');

    $query = match ($this->status) {
        'published' => $query->published(),
        'draft' => $query->where('is_published', false),
        'featured' => $query->featured(),
        'private' => $query->where('hidden_from_guests', true),
        default => $query,
    };

    if ($this->category_id) {
        $query->where('category_id', $this->category_id);
    }

    if ($this->search !== '') {
        $query->search($this->search);
    }

    $articles = $query->paginate(20);
    $categories = KnowledgebaseCategory::query()->ordered()->get();
@endphp

<div>
    <div class="row row-deck row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Published</div>
                    <div class="h1 mb-0">{{ $publishedCount }}</div>
                    <div class="text-secondary">{{ $draftCount }} drafts</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Article views</div>
                    <div class="h1 mb-0">{{ number_format($totalViews) }}</div>
                    <div class="text-secondary">Counted once per visitor per day</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Marked helpful</div>
                    <div class="h1 mb-0">{{ $helpfulPercent }}%</div>
                    <div class="text-secondary">{{ number_format($helpful) }} of {{ number_format($helpful + $unhelpful) }} votes</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Categories</div>
                    <div class="h1 mb-0">{{ $categories->count() }}</div>
                    <div class="text-secondary"><a href="{{ route('admin.knowledgebase.categories.index') }}" wire:navigate>Manage categories</a></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Articles</h3>
            <div class="card-actions">
                <div class="d-flex flex-wrap gap-2">
                    <select class="form-select form-select-sm" wire:model.live="status">
                        <option value="all">All statuses</option>
                        <option value="published">Published</option>
                        <option value="draft">Drafts</option>
                        <option value="featured">Featured</option>
                        <option value="private">Clients only</option>
                    </select>
                    <select class="form-select form-select-sm" wire:model.live="category_id">
                        <option value="">All categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <input type="search" class="form-control form-control-sm" style="min-width: 14rem;" wire:model.live.debounce.300ms="search" placeholder="Search articles">
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Category</th>
                        <th>Views</th>
                        <th>Helpful</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($articles as $article)
                        <tr wire:key="article-{{ $article->id }}">
                            <td>
                                <a href="{{ route('admin.knowledgebase.articles.edit', $article) }}" wire:navigate>{{ $article->title }}</a>
                                <div class="text-secondary">{{ $article->slug }}</div>
                            </td>
                            <td>{{ $article->category?->name }}</td>
                            <td>{{ number_format($article->views_count) }}</td>
                            <td>
                                @if($article->helpful_count + $article->unhelpful_count > 0)
                                    {{ $article->helpfulPercent() }}%
                                    <div class="text-secondary">{{ $article->helpful_count }} / {{ $article->unhelpful_count }}</div>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                            <td>
                                @if($article->is_published)
                                    <span class="badge bg-green-lt">Published</span>
                                @else
                                    <span class="badge bg-secondary-lt">Draft</span>
                                @endif
                                @if($article->is_featured)
                                    <span class="badge bg-blue-lt">Featured</span>
                                @endif
                                @if($article->hidden_from_guests)
                                    <span class="badge bg-orange-lt">Clients only</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($article->is_published && $article->category)
                                    <a href="{{ $article->clientUrl() }}" target="_blank" class="me-2">View</a>
                                @endif
                                @perm('admin.knowledgebase.update')
                                    <a href="{{ route('admin.knowledgebase.articles.edit', $article) }}" wire:navigate>Edit</a>
                                @endperm
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-secondary">No articles match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($articles->hasPages())
            <div class="card-footer">
                {{ $articles->links() }}
            </div>
        @endif
    </div>
</div>
