<?php

namespace Extensions\Modules\Marketplace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceResourceView extends Model
{
    protected $table = 'marketplace_resource_views';

    protected $fillable = [
        'resource_id',
        'user_id',
        'visitor_hash',
    ];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(MarketplaceResource::class, 'resource_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
