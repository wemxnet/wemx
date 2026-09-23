<?php

namespace Extensions\Modules\Marketplace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceDownload extends Model
{
    protected $table = 'marketplace_downloads';

    protected $fillable = [
        'resource_id',
        'version_id',
        'user_id',
        'license_id',
        'ip_address',
        'user_agent',
        'source',
    ];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(MarketplaceResource::class, 'resource_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(MarketplaceResourceVersion::class, 'version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(MarketplaceLicense::class, 'license_id');
    }
}
