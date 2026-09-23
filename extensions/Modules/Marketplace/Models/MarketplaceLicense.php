<?php

namespace Extensions\Modules\Marketplace\Models;

use App\Models\User;
use Extensions\Modules\Marketplace\Actions\MarketplaceLicenseActions;
use Extensions\Modules\Marketplace\Enums\LicenseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MarketplaceLicense extends Model
{
    protected $table = 'marketplace_licenses';

    protected $fillable = [
        'resource_id',
        'sale_id',
        'user_id',
        'granted_by',
        'license_key',
        'domain',
        'status',
        'source',
        'payment_method',
        'transaction_id',
        'purchased_at',
        'max_activations',
        'activations_count',
        'expires_at',
        'last_validated_at',
    ];

    protected $attributes = [
        'status' => 'active',
        'source' => 'purchase',
        'max_activations' => 1,
        'activations_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'max_activations' => 'integer',
            'activations_count' => 'integer',
            'purchased_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_validated_at' => 'datetime',
        ];
    }

    public static function actions(): MarketplaceLicenseActions
    {
        return new MarketplaceLicenseActions;
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(MarketplaceResource::class, 'resource_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSale::class, 'sale_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LicenseStatus::Active)
            ->where(function (Builder $inner) {
                $inner->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! $search) {
            return $query;
        }

        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        return $query->where(function (Builder $inner) use ($term) {
            $inner->where('license_key', 'like', $term)
                ->orWhere('payment_method', 'like', $term)
                ->orWhere('transaction_id', 'like', $term)
                ->orWhere('source', 'like', $term)
                ->orWhereHas('user', function (Builder $user) use ($term) {
                    $user->where('username', 'like', $term)
                        ->orWhere('email', 'like', $term);
                })
                ->orWhereHas('resource', fn (Builder $resource) => $resource->where('name', 'like', $term))
                ->orWhereHas('sale', function (Builder $sale) use ($term) {
                    $sale->where('gateway_reference', 'like', $term)
                        ->orWhere('driver', 'like', $term);
                });
        });
    }

    public function isUsable(): bool
    {
        if ($this->status !== LicenseStatus::Active) {
            return false;
        }

        return ! $this->expires_at || $this->expires_at->isFuture();
    }

    public function paymentMethodLabel(): string
    {
        $method = trim((string) ($this->payment_method ?: $this->sale?->driver));

        if ($method === '') {
            return match ($this->source) {
                'free' => 'Free',
                'manual' => 'Manual',
                default => '—',
            };
        }

        return match (Str::lower($method)) {
            'stripe' => 'Stripe',
            'paypal_ipn', 'paypal' => 'PayPal',
            'manual' => 'Manual',
            'free' => 'Free',
            default => Str::headline($method),
        };
    }

    public function transactionReference(): ?string
    {
        return $this->transaction_id ?: $this->sale?->gateway_reference;
    }

    public function purchasedAt(): ?Carbon
    {
        return $this->purchased_at ?? $this->sale?->paid_at ?? $this->created_at;
    }

    public static function generateKey(): string
    {
        do {
            $key = 'WX-'.collect(range(1, 4))
                ->map(fn () => strtoupper(Str::random(4)))
                ->implode('-');
        } while (static::query()->where('license_key', $key)->exists());

        return $key;
    }
}
