<?php

namespace Extensions\Modules\Marketplace\Models;

use App\Models\User;
use Extensions\Modules\Marketplace\Actions\MarketplaceResourceActions;
use Extensions\Modules\Marketplace\Enums\ResourceStatus;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Enums\VersionStatus;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketplaceResource extends Model
{
    protected $table = 'marketplace_resources';

    protected $fillable = [
        'user_id',
        'category_id',
        'gateway_config_id',
        'name',
        'slug',
        'short_description',
        'description',
        'icon_disk',
        'icon_path',
        'website_url',
        'docs_url',
        'source_url',
        'support_url',
        'price',
        'currency',
        'license_type',
        'tags',
        'available_on_integrated_marketplace',
        'status',
        'rejection_reason',
        'is_featured',
        'is_official',
        'is_disabled',
        'featured_until',
        'views_count',
        'downloads_count',
        'purchases_count',
        'reviews_count',
        'reviews_avg',
        'published_at',
        'approved_at',
        'approved_by',
    ];

    protected $attributes = [
        'price' => 0,
        'currency' => 'USD',
        'license_type' => 'proprietary',
        'available_on_integrated_marketplace' => true,
        'status' => 'pending',
        'is_featured' => false,
        'is_official' => false,
        'is_disabled' => false,
        'views_count' => 0,
        'downloads_count' => 0,
        'purchases_count' => 0,
        'reviews_count' => 0,
        'reviews_avg' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:8',
            'tags' => 'array',
            'available_on_integrated_marketplace' => 'boolean',
            'status' => ResourceStatus::class,
            'is_featured' => 'boolean',
            'is_official' => 'boolean',
            'is_disabled' => 'boolean',
            'featured_until' => 'datetime',
            'views_count' => 'integer',
            'downloads_count' => 'integer',
            'purchases_count' => 'integer',
            'reviews_count' => 'integer',
            'reviews_avg' => 'float',
            'published_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public static function actions(): MarketplaceResourceActions
    {
        return new MarketplaceResourceActions;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function gatewayConfig(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCreatorGatewayConfig::class, 'gateway_config_id');
    }

    public function gatewayConfigs(): BelongsToMany
    {
        return $this->belongsToMany(
            MarketplaceCreatorGatewayConfig::class,
            'marketplace_resource_gateways',
            'resource_id',
            'gateway_config_id',
        )->withTimestamps();
    }

    public function enabledGatewayConfigs(): BelongsToMany
    {
        return $this->gatewayConfigs()->enabled()->orderBy('name');
    }

    public function hasEnabledPaymentMethods(): bool
    {
        if ($this->relationLoaded('gatewayConfigs')) {
            return $this->gatewayConfigs->contains(fn (MarketplaceCreatorGatewayConfig $config) => $config->is_enabled);
        }

        return $this->gatewayConfigs()->enabled()->exists();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MarketplaceResourceVersion::class, 'resource_id')
            ->orderByDesc('created_at');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(MarketplaceResourceTeamMember::class, 'resource_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(MarketplaceSale::class, 'resource_id');
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(MarketplaceLicense::class, 'resource_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(MarketplaceResourceReview::class, 'resource_id');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(MarketplaceDownload::class, 'resource_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(MarketplaceResourceView::class, 'resource_id');
    }

    public function latestVersion(): ?MarketplaceResourceVersion
    {
        return $this->versions()
            ->orderByDesc('created_at')
            ->first();
    }

    public function latestApprovedVersion(): ?MarketplaceResourceVersion
    {
        if ($this->status === ResourceStatus::Approved) {
            return $this->latestVersion();
        }

        return $this->versions()
            ->where('status', VersionStatus::Approved)
            ->orderByDesc('created_at')
            ->first();
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ResourceStatus::Approved);
    }

    public function scopeListed(Builder $query): Builder
    {
        return $query->approved()->where('is_disabled', false);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true)
            ->where(function (Builder $inner) {
                $inner->whereNull('featured_until')
                    ->orWhere('featured_until', '>', now());
            });
    }

    public function scopeOfficial(Builder $query): Builder
    {
        return $query->where('is_official', true);
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')
            ->orderByDesc('is_official')
            ->orderByRaw('(views_count + downloads_count) desc')
            ->orderByDesc('purchases_count')
            ->orderByDesc('id');
    }

    public function scopeSortedBy(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'popular_free' => $query->where('price', '<=', 0)->popular(),
            'updated' => $query->orderByLastUpdated(),
            'downloads' => $query->orderByDesc('downloads_count')->orderByDesc('id'),
            'purchases' => $query->orderByDesc('purchases_count')->orderByDesc('id'),
            default => $query->popular(),
        };
    }

    public function scopeOrderByLastUpdated(Builder $query): Builder
    {
        return $query
            ->orderByDesc(
                MarketplaceResourceVersion::query()
                    ->select('created_at')
                    ->whereColumn('marketplace_resource_versions.resource_id', 'marketplace_resources.id')
                    ->orderByDesc('created_at')
                    ->limit(1)
            )
            ->orderByDesc('marketplace_resources.id');
    }

    public function scopeAuthoredBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeCollaboratedBy(Builder $query, User $user): Builder
    {
        return $query
            ->where('user_id', '!=', $user->id)
            ->whereHas('teamMembers', fn (Builder $team) => $team->where('user_id', $user->id));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function browseSortOptions(): array
    {
        return [
            ['value' => 'popular', 'label' => 'Popular (All)'],
            ['value' => 'popular_free', 'label' => 'Popular (Free)'],
            ['value' => 'updated', 'label' => 'Last Updated'],
            ['value' => 'downloads', 'label' => 'Most Downloads'],
            ['value' => 'purchases', 'label' => 'Most Purchases'],
        ];
    }

    public static function authorProfileUrl(User|string $user): string
    {
        $username = $user instanceof User ? $user->username : $user;

        return url('/marketplace/authors/'.$username);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! $search) {
            return $query;
        }

        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        return $query->where(function (Builder $inner) use ($term) {
            $inner->where('name', 'like', $term)
                ->orWhere('short_description', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhere('tags', 'like', $term);
        });
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($this->staffCanManage($user)) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user) {
            $inner->where(function (Builder $listed) {
                $listed->where('status', ResourceStatus::Approved)
                    ->where('is_disabled', false);
            });

            if ($user) {
                $inner->orWhere('user_id', $user->id)
                    ->orWhereHas('teamMembers', fn (Builder $team) => $team->where('user_id', $user->id));
            }
        });
    }

    public function scopeIntegrated(Builder $query): Builder
    {
        return $query->listed()->where('available_on_integrated_marketplace', true);
    }

    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    public function isListedPublicly(): bool
    {
        return $this->status === ResourceStatus::Approved && ! $this->is_disabled;
    }

    public function canBeDeleted(): bool
    {
        if ($this->isFree()) {
            return true;
        }

        return (int) $this->purchases_count === 0;
    }

    public function canBeReviewedBy(?User $user): bool
    {
        if (! $user || ! $this->isListedPublicly()) {
            return false;
        }

        if ($this->isFree()) {
            return true;
        }

        return MarketplaceLicense::query()
            ->where('resource_id', $this->id)
            ->where('user_id', $user->id)
            ->active()
            ->exists();
    }

    public function formattedRating(): string
    {
        if ($this->reviews_count < 1) {
            return 'No reviews';
        }

        return number_format((float) $this->reviews_avg, 1).' · '.$this->reviews_count.' '.Str::plural('review', $this->reviews_count);
    }

    public function isFeaturedNow(): bool
    {
        if (! $this->is_featured) {
            return false;
        }

        return ! $this->featured_until || $this->featured_until->isFuture();
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($this->staffCanManage($user)) {
            return true;
        }

        if ($user && $this->teamRoleFor($user) !== null) {
            return true;
        }

        if ($this->isListedPublicly()) {
            return true;
        }

        if ($user && $this->status === ResourceStatus::Approved && $this->is_disabled) {
            return MarketplaceLicense::query()
                ->where('resource_id', $this->id)
                ->where('user_id', $user->id)
                ->active()
                ->exists();
        }

        return false;
    }

    public function teamRoleFor(?User $user): ?TeamRole
    {
        if (! $user) {
            return null;
        }

        if ((int) $this->user_id === (int) $user->id) {
            return TeamRole::Owner;
        }

        $member = $this->relationLoaded('teamMembers')
            ? $this->teamMembers->firstWhere('user_id', $user->id)
            : $this->teamMembers()->where('user_id', $user->id)->first();

        return $member?->role;
    }

    public function userCan(User $user, TeamRole $minimum = TeamRole::Support): bool
    {
        if ($this->staffCanManage($user)) {
            return true;
        }

        $role = $this->teamRoleFor($user);

        return $role?->atLeast($minimum) ?? false;
    }

    public function formattedPrice(): string
    {
        if ($this->isFree()) {
            return 'Free';
        }

        try {
            return price((float) $this->price, $this->currency, $this->currency);
        } catch (\Throwable) {
            return number_format((float) $this->price, 2).' '.$this->currency;
        }
    }

    public function iconDisk(): Filesystem
    {
        return Storage::disk($this->icon_disk ?: 'public');
    }

    public function iconUrl(): ?string
    {
        if (! $this->icon_path || ! $this->icon_disk) {
            return null;
        }

        if ($this->icon_disk === 'public') {
            return Storage::disk('public')->url($this->icon_path);
        }

        return url('/marketplace/icons/'.$this->slug);
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $words = array_values(array_filter($words, fn (string $word): bool => $word !== ''));

        if (count($words) >= 2) {
            return Str::upper(Str::substr($words[0], 0, 1).Str::substr($words[1], 0, 1));
        }

        $word = $words[0] ?? 'R';

        return Str::upper(Str::substr($word, 0, min(2, Str::length($word))));
    }

    public function renderedDescription(): string
    {
        return Str::markdown($this->description ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function clientUrl(): string
    {
        return url('/marketplace/'.$this->category?->slug.'/'.$this->slug);
    }

    public function studioUrl(string $section = 'resource'): string
    {
        $base = '/marketplace/studio/resources/'.$this->slug;

        return url(match ($section) {
            'versions' => $base.'/versions',
            'licenses' => $base.'/licenses',
            'team' => $base.'/team',
            default => $base,
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toIntegratedArray(bool $includeReviews = false, bool $summary = false): array
    {
        $this->loadMissing(['category', 'author', 'versions']);

        $versions = $this->versions
            ->when(
                $this->status === ResourceStatus::Approved,
                fn ($collection) => $collection->reject(fn (MarketplaceResourceVersion $version) => $version->status === VersionStatus::Rejected),
                fn ($collection) => $collection->where('status', VersionStatus::Approved),
            )
            ->sortByDesc('created_at')
            ->values();

        $payload = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'icon' => $this->iconUrl(),
            'initials' => $this->initials(),
            'price' => $this->formattedPrice(),
            'featured' => $this->isFeaturedNow(),
            'official' => (bool) $this->is_official,
            'views' => $this->views_count,
            'downloads' => $this->downloads_count,
            'purchases' => $this->purchases_count,
            'reviews_count' => $this->reviews_count,
            'reviews_avg' => (float) $this->reviews_avg,
            'latest_version' => $versions->first()?->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'view_url' => $this->clientUrl(),
            'category' => [
                'id' => $this->category?->id,
                'slug' => $this->category?->slug,
                'name' => $this->category?->name,
            ],
            'user' => [
                'username' => $this->author?->username,
                'avatar' => $this->author?->getAvatarUrl(),
                'url' => $this->author ? static::authorProfileUrl($this->author) : null,
            ],
        ];

        if ($summary) {
            return $payload;
        }

        $payload['description'] = $this->description;
        $payload['source'] = $this->source_url;
        $payload['website'] = $this->website_url;
        $payload['docs'] = $this->docs_url;
        $payload['support'] = $this->support_url;
        $payload['versions'] = $versions
            ->map(fn (MarketplaceResourceVersion $version) => $version->toIntegratedArray())
            ->all();

        if ($includeReviews) {
            $this->loadMissing(['reviews.user']);

            $payload['reviews'] = $this->reviews
                ->where('is_visible', true)
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (MarketplaceResourceReview $review) => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'title' => $review->title,
                    'body' => $review->body,
                    'created_at' => $review->created_at?->toIso8601String(),
                    'user' => [
                        'username' => $review->user?->username,
                        'avatar' => $review->user?->getAvatarUrl(),
                    ],
                ])
                ->all();
        }

        return $payload;
    }

    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name) ?: 'resource';
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

    /**
     * @return list<string>
     */
    public static function normalizeTags(array|string|null $tags): array
    {
        $items = is_string($tags)
            ? preg_split('/[,]+/', $tags) ?: []
            : ($tags ?? []);

        return collect($items)
            ->map(fn ($tag) => Str::slug(trim((string) $tag)))
            ->filter()
            ->unique()
            ->take(16)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function licenseTypes(): array
    {
        return ['proprietary', 'mit', 'gpl-3.0', 'apache-2.0', 'custom'];
    }

    public static function visitorHash(?User $user = null): string
    {
        if ($user) {
            return hash('sha256', 'user:'.$user->id);
        }

        $sessionId = session()->getId() ?: (string) request()->ip();

        return hash('sha256', 'guest:'.$sessionId);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function staffCanManage(?User $user): bool
    {
        return (bool) ($user?->isStaff() && $user->hasPermission('admin.marketplace.manage'));
    }
}
