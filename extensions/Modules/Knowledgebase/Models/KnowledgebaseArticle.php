<?php

namespace Extensions\Modules\Knowledgebase\Models;

use App\Models\User;
use Extensions\Modules\Knowledgebase\Actions\KnowledgebaseArticleActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class KnowledgebaseArticle extends Model
{
    protected $table = 'knowledgebase_articles';

    protected $fillable = [
        'category_id',
        'author_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'tags',
        'is_published',
        'is_featured',
        'hidden_from_guests',
        'views_count',
        'helpful_count',
        'unhelpful_count',
        'published_at',
        'sort_order',
    ];

    protected $attributes = [
        'is_published' => true,
        'is_featured' => false,
        'hidden_from_guests' => false,
        'views_count' => 0,
        'helpful_count' => 0,
        'unhelpful_count' => 0,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'hidden_from_guests' => 'boolean',
            'views_count' => 'integer',
            'helpful_count' => 'integer',
            'unhelpful_count' => 'integer',
            'published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public static function actions(): KnowledgebaseArticleActions
    {
        return new KnowledgebaseArticleActions;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgebaseCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(KnowledgebaseArticleView::class, 'article_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(KnowledgebaseArticleVote::class, 'article_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query->orderByDesc('views_count')->orderByDesc('helpful_count');
    }

    public function scopeRecentlyUpdated(Builder $query): Builder
    {
        return $query->orderByDesc('updated_at');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! $search) {
            return $query;
        }

        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        return $query->where(function (Builder $inner) use ($term) {
            $inner->where('title', 'like', $term)
                ->orWhere('excerpt', 'like', $term)
                ->orWhere('content', 'like', $term)
                ->orWhere('tags', 'like', $term);
        });
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($this->staffCanManage($user)) {
            return $query;
        }

        $query->published();

        if (! $user) {
            $query->where('hidden_from_guests', false);
        }

        return $query->whereHas('category', fn (Builder $category) => $category->visibleTo($user));
    }

    public function isVisibleTo(?User $user, bool $checkCategory = true): bool
    {
        if ($this->staffCanManage($user)) {
            return true;
        }

        if (! $this->is_published) {
            return false;
        }

        if ($this->hidden_from_guests && ! $user) {
            return false;
        }

        if ($checkCategory && $this->category && ! $this->category->isVisibleTo($user)) {
            return false;
        }

        return true;
    }

    public function clientUrl(): string
    {
        return route('knowledgebase.article', [
            'category' => $this->category,
            'article' => $this,
        ]);
    }

    public function renderedContent(): string
    {
        return static::renderMarkdown($this->content);
    }

    /**
     * @return list<array{level: int, text: string, id: string}>
     */
    public function tableOfContents(): array
    {
        preg_match_all('/^(#{2,3})\s+(.+)$/m', $this->content ?? '', $matches, PREG_SET_ORDER);

        return collect($matches)->map(function (array $match) {
            $text = trim($match[2]);

            return [
                'level' => strlen($match[1]),
                'text' => $text,
                'id' => Str::slug($text),
            ];
        })->all();
    }

    public function related(?User $user = null, int $limit = 5): Collection
    {
        $tags = collect($this->tags ?? [])->filter()->values();

        return static::query()
            ->with('category')
            ->visibleTo($user)
            ->where('id', '!=', $this->id)
            ->where(function (Builder $inner) use ($tags) {
                $inner->where('category_id', $this->category_id);

                foreach ($tags as $tag) {
                    $inner->orWhereJsonContains('tags', $tag);
                }
            })
            ->popular()
            ->limit($limit)
            ->get();
    }

    public function helpfulPercent(): int
    {
        $total = $this->helpful_count + $this->unhelpful_count;

        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->helpful_count / $total) * 100);
    }

    public function displayExcerpt(): string
    {
        if ($this->excerpt) {
            return $this->excerpt;
        }

        return static::excerptFrom($this->content);
    }

    public static function excerptFrom(?string $content, int $length = 180): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags(static::renderMarkdown($content))) ?? '');

        return Str::limit($plain, $length);
    }

    public static function renderMarkdown(?string $body): string
    {
        $html = Str::markdown($body ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return preg_replace_callback('/<h([23])>(.*?)<\/h\1>/s', function (array $match) {
            $id = Str::slug(strip_tags($match[2]));

            return '<h'.$match[1].' id="'.e($id).'">'.$match[2].'</h'.$match[1].'>';
        }, $html) ?? $html;
    }

    public static function generateSlug(string $title, ?int $ignoreId = null): string
    {
        $slug = Str::slug($title) ?: 'article';
        $base = $slug;
        $i = 1;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public static function nextSortOrder(int $categoryId): int
    {
        return (int) static::query()->where('category_id', $categoryId)->max('sort_order') + 10;
    }

    public static function visitorHash(?User $user = null): string
    {
        if ($user) {
            return hash('sha256', 'user:'.$user->id);
        }

        $sessionId = session()->getId() ?: (string) request()->ip();

        return hash('sha256', 'guest:'.$sessionId);
    }

    /**
     * @return list<string>
     */
    public static function normalizeTags(array|string|null $tags): array
    {
        $items = is_string($tags)
            ? preg_split('/[,]+/', $tags) ?: []
            : ($tags ?? []);

        return collect($items)
            ->map(fn ($tag) => Str::slug(trim((string) $tag)))
            ->filter()
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    protected function staffCanManage(?User $user): bool
    {
        return (bool) ($user?->isStaff() && $user->hasPermission('admin.knowledgebase'));
    }
}
