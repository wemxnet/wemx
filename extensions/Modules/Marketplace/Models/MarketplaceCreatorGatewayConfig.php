<?php

namespace Extensions\Modules\Marketplace\Models;

use App\Models\User;
use Extensions\Modules\Marketplace\Actions\MarketplaceCreatorGatewayActions;
use Extensions\Modules\Marketplace\Gateways\CreatorGatewayFoundation;
use Extensions\Modules\Marketplace\Gateways\CreatorGatewayRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceCreatorGatewayConfig extends Model
{
    protected $table = 'marketplace_creator_gateway_configs';

    protected $fillable = [
        'user_id',
        'name',
        'driver',
        'credentials',
        'settings',
        'is_enabled',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected $attributes = [
        'is_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public static function actions(): MarketplaceCreatorGatewayActions
    {
        return new MarketplaceCreatorGatewayActions;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(MarketplaceResource::class, 'gateway_config_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(MarketplaceSale::class, 'gateway_config_id');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials ?? [], $key, $default);
    }

    public function driver(): CreatorGatewayFoundation
    {
        return CreatorGatewayRegistry::make($this->driver);
    }

    public function driverName(): string
    {
        return CreatorGatewayRegistry::label($this->driver);
    }
}
