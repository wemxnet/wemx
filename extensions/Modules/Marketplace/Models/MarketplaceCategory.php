<?php

namespace Extensions\Modules\Marketplace\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceCategory extends Model
{
    public const INTEGRATED_SLUGS = ['server', 'module', 'payment-gateway'];

    protected $table = 'marketplace_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'default_extract_path',
        'sort_order',
        'is_visible',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'is_visible' => true,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function resources(): HasMany
    {
        return $this->hasMany(MarketplaceResource::class, 'category_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeIntegrated(Builder $query): Builder
    {
        return $query->whereIn('slug', self::INTEGRATED_SLUGS);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function supportsIntegratedInstall(): bool
    {
        return in_array($this->slug, self::INTEGRATED_SLUGS, true);
    }
}
