<?php

namespace Extensions\Modules\Downloads\Models;

use App\Models\User;
use Extensions\Modules\Downloads\Actions\DownloadFolderActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DownloadFolder extends Model
{
    protected $table = 'download_folders';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_visible',
        'sort_order',
    ];

    protected $attributes = [
        'is_visible' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public static function actions(): DownloadFolderActions
    {
        return new DownloadFolderActions;
    }

    public function files(): HasMany
    {
        return $this->hasMany(DownloadFile::class, 'folder_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function isVisible(): bool
    {
        return $this->is_visible;
    }

    public function filesVisibleTo(?User $user): Collection
    {
        return $this->files
            ->sortBy([
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])
            ->filter(fn (DownloadFile $file) => $file->isVisibleTo($user))
            ->values();
    }

    public function isVisibleTo(?User $user): bool
    {
        if (! $this->is_visible) {
            return false;
        }

        return $this->filesVisibleTo($user)->isNotEmpty();
    }

    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name) ?: 'folder';
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

    public static function nextSortOrder(): int
    {
        return (int) static::query()->max('sort_order') + 10;
    }
}
