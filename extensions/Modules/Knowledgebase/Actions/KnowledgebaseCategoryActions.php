<?php

namespace Extensions\Modules\Knowledgebase\Actions;

use App\Actions\Action;
use App\Models\User;
use Extensions\Modules\Knowledgebase\Models\KnowledgebaseCategory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KnowledgebaseCategoryActions extends Action
{
    public function createAsAdmin(array $input): KnowledgebaseCategory
    {
        $validated = Validator::make($input, $this->rules())->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.knowledgebase.create');
        unset($validated['admin_user_id']);

        $this->assertParentIsRoot($validated['parent_id'] ?? null);

        $validated['slug'] = KnowledgebaseCategory::generateSlug($validated['slug'] ?? $validated['name']);
        $validated['sort_order'] ??= KnowledgebaseCategory::nextSortOrder($validated['parent_id'] ?? null);

        return KnowledgebaseCategory::create(self::omitNullValues($validated));
    }

    public function updateAsAdmin(array $input): KnowledgebaseCategory
    {
        $validated = Validator::make($input, array_merge($this->rules(updating: true), [
            'category_id' => ['required', 'integer', 'exists:knowledgebase_categories,id'],
        ]))->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.knowledgebase.update');

        $category = KnowledgebaseCategory::findOrFail($validated['category_id']);

        unset($validated['admin_user_id'], $validated['category_id']);

        if (array_key_exists('parent_id', $validated)) {
            $this->assertParentIsRoot($validated['parent_id'], $category);
        }

        if (isset($validated['name']) || isset($validated['slug'])) {
            $validated['slug'] = KnowledgebaseCategory::generateSlug(
                $validated['slug'] ?? $validated['name'] ?? $category->name,
                $category->id,
            );
        }

        $category->update(self::omitNullValues($validated));

        return $category->fresh();
    }

    public function deleteAsAdmin(array $input): bool
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'category_id' => ['required', 'integer', 'exists:knowledgebase_categories,id'],
        ])->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.knowledgebase.delete');

        $category = KnowledgebaseCategory::query()->withCount(['articles', 'children'])->findOrFail($validated['category_id']);

        if ($category->articles_count > 0) {
            throw ValidationException::withMessages([
                'category_id' => 'Move or delete the articles in this category first.',
            ]);
        }

        if ($category->children_count > 0) {
            throw ValidationException::withMessages([
                'category_id' => 'Move or delete the child categories first.',
            ]);
        }

        return (bool) $category->delete();
    }

    public function moveAsAdmin(array $input): KnowledgebaseCategory
    {
        $validated = Validator::make($input, [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'category_id' => ['required', 'integer', 'exists:knowledgebase_categories,id'],
            'direction' => ['required', 'in:up,down'],
        ])->validate();

        $this->staffUser($validated['admin_user_id'], 'admin.knowledgebase.update');

        $category = KnowledgebaseCategory::findOrFail($validated['category_id']);
        $ordered = KnowledgebaseCategory::query()
            ->when(
                $category->parent_id,
                fn ($query) => $query->where('parent_id', $category->parent_id),
                fn ($query) => $query->whereNull('parent_id'),
            )
            ->ordered()
            ->get();

        $index = $ordered->search(fn (KnowledgebaseCategory $item) => $item->id === $category->id);

        if ($index === false) {
            return $category;
        }

        $swapWith = $validated['direction'] === 'up'
            ? $ordered->get($index - 1)
            : $ordered->get($index + 1);

        if (! $swapWith) {
            return $category;
        }

        $currentOrder = $category->sort_order;
        $category->update(['sort_order' => $swapWith->sort_order]);
        $swapWith->update(['sort_order' => $currentOrder]);

        if ($category->sort_order === $swapWith->sort_order) {
            $category->update([
                'sort_order' => $validated['direction'] === 'up'
                    ? $swapWith->sort_order - 1
                    : $swapWith->sort_order + 1,
            ]);
        }

        return $category->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'admin_user_id' => ['required', 'integer', 'exists:users,id'],
            'parent_id' => ['nullable', 'integer', 'exists:knowledgebase_categories,id'],
            'name' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', Rule::in(KnowledgebaseCategory::icons())],
            'is_visible' => ['sometimes', 'boolean'],
            'hidden_from_guests' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }

    protected function assertParentIsRoot(?int $parentId, ?KnowledgebaseCategory $category = null): void
    {
        if (! $parentId) {
            return;
        }

        if ($category && $parentId === $category->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be its own parent.',
            ]);
        }

        $parent = KnowledgebaseCategory::find($parentId);

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_id' => 'The selected parent category is invalid.',
            ]);
        }

        if ($parent->parent_id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Categories can only be nested one level deep.',
            ]);
        }

        if ($category && $category->children()->exists()) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category with children cannot become a subcategory.',
            ]);
        }
    }

    protected function staffUser(int $userId, string $permission): User
    {
        $user = User::find($userId);

        if (! $user || ! $user->isStaff() || ! $user->hasPermission($permission)) {
            throw ValidationException::withMessages([
                'admin_user_id' => 'You do not have permission to manage knowledgebase categories.',
            ]);
        }

        return $user;
    }
}
