<?php

namespace Extensions\Modules\Knowledgebase\Models;

use App\Models\User;
use Extensions\Modules\Knowledgebase\Actions\KnowledgebaseCategoryActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;

class KnowledgebaseCategory extends Model
{
    protected $table = 'knowledgebase_categories';

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'icon',
        'is_visible',
        'hidden_from_guests',
        'sort_order',
    ];

    protected $attributes = [
        'is_visible' => true,
        'hidden_from_guests' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'hidden_from_guests' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public static function actions(): KnowledgebaseCategoryActions
    {
        return new KnowledgebaseCategoryActions;
    }

    public static function icons(): array
    {
        return ['book', 'credit-card', 'server', 'wrench', 'life-buoy', 'folder'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->ordered();
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgebaseArticle::class, 'category_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($this->staffCanManage($user)) {
            return $query;
        }

        $query->visible();

        if (! $user) {
            $query->where('hidden_from_guests', false);
        }

        return $query;
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($this->staffCanManage($user)) {
            return true;
        }

        if (! $this->is_visible) {
            return false;
        }

        if ($this->hidden_from_guests && ! $user) {
            return false;
        }

        if ($this->parent && ! $this->parent->isVisibleTo($user)) {
            return false;
        }

        return true;
    }

    public function articlesVisibleTo(?User $user): Collection
    {
        return $this->articles
            ->sortBy([
                ['sort_order', 'asc'],
                ['title', 'asc'],
            ])
            ->filter(fn (KnowledgebaseArticle $article) => $article->isVisibleTo($user, checkCategory: false))
            ->values();
    }

    public function clientUrl(): string
    {
        return route('knowledgebase.category', $this);
    }

    public function breadcrumbs(): SupportCollection
    {
        $trail = collect([$this]);
        $current = $this;

        while ($current->parent) {
            $current = $current->parent;
            $trail->prepend($current);
        }

        return $trail;
    }

    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name) ?: 'category';
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

    public static function nextSortOrder(?int $parentId = null): int
    {
        return (int) static::query()
            ->when(
                $parentId,
                fn (Builder $query) => $query->where('parent_id', $parentId),
                fn (Builder $query) => $query->whereNull('parent_id'),
            )
            ->max('sort_order') + 10;
    }

    public static function treeForAdmin(): Collection
    {
        return static::query()
            ->with(['children' => fn ($query) => $query->withCount('articles')->ordered()])
            ->withCount('articles')
            ->roots()
            ->ordered()
            ->get();
    }

    public static function optionsForSelect(?int $ignoreId = null): Collection
    {
        return static::query()
            ->roots()
            ->ordered()
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
            ->get();
    }

    protected function staffCanManage(?User $user): bool
    {
        return (bool) ($user?->isStaff() && $user->hasPermission('admin.knowledgebase'));
    }
}
