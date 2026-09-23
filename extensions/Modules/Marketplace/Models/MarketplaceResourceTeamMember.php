<?php

namespace Extensions\Modules\Marketplace\Models;

use App\Models\User;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceResourceTeamMember extends Model
{
    protected $table = 'marketplace_resource_team_members';

    protected $fillable = [
        'resource_id',
        'user_id',
        'role',
        'invited_by',
    ];

    protected $attributes = [
        'role' => 'developer',
    ];

    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(MarketplaceResource::class, 'resource_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
