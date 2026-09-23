<?php

namespace Extensions\Modules\Marketplace\Models;

use App\Models\User;
use Extensions\Modules\Marketplace\Actions\MarketplaceSaleActions;
use Extensions\Modules\Marketplace\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class MarketplaceSale extends Model
{
    protected $table = 'marketplace_sales';

    protected $fillable = [
        'uuid',
        'resource_id',
        'version_id',
        'seller_id',
        'buyer_id',
        'gateway_config_id',
        'driver',
        'amount',
        'currency',
        'status',
        'gateway_reference',
        'gateway_payload',
        'paid_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'currency' => 'USD',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'status' => SaleStatus::class,
            'gateway_payload' => 'encrypted:array',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $sale) {
            $sale->uuid ??= (string) Str::uuid();
        });
    }

    public static function actions(): MarketplaceSaleActions
    {
        return new MarketplaceSaleActions;
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(MarketplaceResource::class, 'resource_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(MarketplaceResourceVersion::class, 'version_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function gatewayConfig(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCreatorGatewayConfig::class, 'gateway_config_id');
    }

    public function license(): HasOne
    {
        return $this->hasOne(MarketplaceLicense::class, 'sale_id');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', SaleStatus::Completed);
    }

    public function isCompleted(): bool
    {
        return $this->status === SaleStatus::Completed;
    }

    public function formattedAmount(): string
    {
        try {
            return price((float) $this->amount, $this->currency, $this->currency);
        } catch (\Throwable) {
            return number_format((float) $this->amount, 2).' '.$this->currency;
        }
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
