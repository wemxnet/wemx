<?php

namespace Extensions\Modules\Marketplace\Models;

use App\Models\User;
use Extensions\Modules\Marketplace\Actions\MarketplaceReviewActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceResourceReview extends Model
{
    protected $table = 'marketplace_resource_reviews';

    protected $fillable = [
        'resource_id',
        'user_id',
        'rating',
        'title',
        'body',
        'is_visible',
    ];

    protected $attributes = [
        'is_visible' => true,
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public static function actions(): MarketplaceReviewActions
    {
        return new MarketplaceReviewActions;
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(MarketplaceResource::class, 'resource_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }
}
