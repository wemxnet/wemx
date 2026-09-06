<?php

namespace Extensions\Modules\Knowledgebase\Actions;

use App\Actions\Action;
use App\Models\User;
use Extensions\Modules\Knowledgebase\Models\KnowledgebaseArticle;
use Extensions\Modules\Knowledgebase\Models\KnowledgebaseArticleVote;
use Extensions\Modules\Knowledgebase\Models\KnowledgebaseCategory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class KnowledgebaseArticleActions extends Action
{
    public function createAsAdmin(array $input): KnowledgebaseArticle
    {
        $validated = Validator::make($input, $this->rules())->validate();

        $admin = $this->staffUser($validated['admin_user_id'], 'admin.knowledgebase.create');
        unset($validated['admin_user_id']);

        $this->assertCategoryExists((int) $validated['category_id']);

        $validated['author_id'] = $admin->id;
        $validated['slug'] = KnowledgebaseArticle::generateSlug($validated['slug'] ?? $validated['title']);
        $validated['tags'] = KnowledgebaseArticle::normalizeTags($validated['tags'] ?? null);
        $validated['excerpt'] = $this->resolveExcerpt($validated['excerpt'] ?? null, $validated['content']);
        $validated['sort_order'] ??= KnowledgebaseArticle::nextSortOrder((int) $validated['category_id']);
        $validated['published_at'] = ($validated['is_published'] ?? true) ? now() : null;

        return KnowledgebaseArticle::create(self::omitNullValues($validated));
    }

    public function updateAsAdmin(array $input): KnowledgebaseArticle
    {
        $validated = Validator::make($input, array_merge($this->rules(updating: true), [
            'article_id' => ['required', 'integer', 'exists:knowledgebase_articles,id'],
        ]))->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.knowledgebase.update');

        $article = KnowledgebaseArticle::findOrFail($validated['article_id']);

        unset($validated['admin_user_id'], $validated['article_id']);

        if (isset($validated['category_id'])) {
            $this->assertCategoryExists((int) $validated['category_id']);
        }

        if (isset($validated['title']) || isset($validated['slug'])) {
            $validated['slug'] = KnowledgebaseArticle::generateSlug(
                $validated['slug'] ?? $validated['title'] ?? $article->title,
                $article->id,
            );
        }

        if (array_key_exists('tags', $validated)) {
            $validated['tags'] = KnowledgebaseArticle::normalizeTags($validated['tags']);
        }

        if (array_key_exists('excerpt', $validated) || isset($validated['content'])) {
            $validated['excerpt'] = $this->resolveExcerpt(
                $validated['excerpt'] ?? $article->excerpt,
                $validated['content'] ?? $article->content,
            );
        }

        if (array_key_exists('is_published', $validated)) {
            if ($validated['is_published'] && ! $article->published_at) {
                $validated['published_at'] = now();
            }

            if (! $validated['is_published']) {
                $validated['published_at'] = null;
            }
        }

        $article->update(self::omitNullValues($validated));

        return $article->fresh();
    }

    public function deleteAsAdmin(array $input): bool
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'article_id' => ['required', 'integer', 'exists:knowledgebase_articles,id'],
        ])->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.knowledgebase.delete');

        $article = KnowledgebaseArticle::findOrFail($validated['article_id']);

        return (bool) $article->delete();
    }

    public function recordView(KnowledgebaseArticle $article, ?User $user = null): KnowledgebaseArticle
    {
        $hash = KnowledgebaseArticle::visitorHash($user);

        $alreadyCounted = $article->views()
            ->where('visitor_hash', $hash)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($alreadyCounted) {
            return $article;
        }

        $article->views()->create([
            'user_id' => $user?->id,
            'visitor_hash' => $hash,
            'created_at' => now(),
        ]);

        $article->increment('views_count');

        return $article->fresh();
    }

    public function vote(array $input): KnowledgebaseArticleVote
    {
        $validated = Validator::make($input, [
            'article_id' => ['required', 'integer', 'exists:knowledgebase_articles,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_helpful' => ['required', 'boolean'],
        ])->validate();

        $article = KnowledgebaseArticle::findOrFail($validated['article_id']);
        $user = isset($validated['user_id']) ? User::find($validated['user_id']) : null;

        if (! $article->isVisibleTo($user)) {
            throw ValidationException::withMessages([
                'article_id' => 'This article is not available.',
            ]);
        }

        $hash = KnowledgebaseArticle::visitorHash($user);
        $existing = $article->votes()->where('visitor_hash', $hash)->first();

        if ($existing && $existing->is_helpful === $validated['is_helpful']) {
            return $existing;
        }

        if ($existing) {
            if ($existing->is_helpful) {
                $article->decrement('helpful_count');
            } else {
                $article->decrement('unhelpful_count');
            }

            $existing->update([
                'is_helpful' => $validated['is_helpful'],
                'user_id' => $user?->id,
            ]);

            $vote = $existing->fresh();
        } else {
            $vote = $article->votes()->create([
                'user_id' => $user?->id,
                'visitor_hash' => $hash,
                'is_helpful' => $validated['is_helpful'],
            ]);
        }

        if ($validated['is_helpful']) {
            $article->increment('helpful_count');
        } else {
            $article->increment('unhelpful_count');
        }

        return $vote;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'category_id' => [$required, 'integer', 'exists:knowledgebase_categories,id'],
            'title' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => [$required, 'string', 'max:100000'],
            'tags' => ['nullable'],
            'is_published' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'hidden_from_guests' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }

    protected function assertCategoryExists(int $categoryId): KnowledgebaseCategory
    {
        $category = KnowledgebaseCategory::find($categoryId);

        if (! $category) {
            throw ValidationException::withMessages([
                'category_id' => 'The selected category is invalid.',
            ]);
        }

        return $category;
    }

    protected function resolveExcerpt(?string $excerpt, string $content): string
    {
        if (filled($excerpt)) {
            return $excerpt;
        }

        return KnowledgebaseArticle::excerptFrom($content);
    }

    protected function staffUser(int $userId, string $permission): User
    {
        $user = User::find($userId);

        if (! $user || ! $user->isStaff() || ! $user->hasPermission($permission)) {
            throw ValidationException::withMessages([
                'admin_user_id' => 'You do not have permission to manage knowledgebase articles.',
            ]);
        }

        return $user;
    }
}
