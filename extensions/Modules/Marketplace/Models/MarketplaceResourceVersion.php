<?php

namespace Extensions\Modules\Marketplace\Models;

use App\Models\User;
use Extensions\Modules\Marketplace\Actions\MarketplaceResourceVersionActions;
use Extensions\Modules\Marketplace\Enums\ResourceStatus;
use Extensions\Modules\Marketplace\Enums\VersionStatus;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketplaceResourceVersion extends Model
{
    protected $table = 'marketplace_resource_versions';

    protected $fillable = [
        'resource_id',
        'user_id',
        'name',
        'version',
        'wemx_version',
        'changelog',
        'available_on_integrated_marketplace',
        'extract_path',
        'rename_extract_to',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'checksum',
        'status',
        'notify_customers',
        'downloads_count',
    ];

    protected $attributes = [
        'wemx_version' => '*',
        'available_on_integrated_marketplace' => true,
        'disk' => 'local',
        'status' => 'pending',
        'notify_customers' => false,
        'downloads_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'available_on_integrated_marketplace' => 'boolean',
            'notify_customers' => 'boolean',
            'size' => 'integer',
            'status' => VersionStatus::class,
            'downloads_count' => 'integer',
        ];
    }

    public static function actions(): MarketplaceResourceVersionActions
    {
        return new MarketplaceResourceVersionActions;
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(MarketplaceResource::class, 'resource_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(MarketplaceDownload::class, 'version_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', VersionStatus::Approved);
    }

    public function scopeIntegrated(Builder $query): Builder
    {
        return $query->approved()->where('available_on_integrated_marketplace', true);
    }

    public function renderedChangelog(): string
    {
        return Str::markdown($this->changelog ?: '*No changelog provided.*', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function humanSize(): string
    {
        $bytes = $this->size;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    public function isDownloadable(?MarketplaceResource $resource = null): bool
    {
        if ($this->status === VersionStatus::Rejected) {
            return false;
        }

        $resource ??= $this->resource;

        if ($resource->status === ResourceStatus::Approved) {
            return true;
        }

        return $this->status === VersionStatus::Approved;
    }

    public function storageDisk(): Filesystem
    {
        return Storage::disk($this->disk ?: 'local');
    }

    /**
     * @return array<string, mixed>
     */
    public function toIntegratedArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'wemx_version' => $this->wemx_version,
            'changelog' => $this->changelog,
            'created_at' => $this->created_at?->toIso8601String(),
            'integrated_marketplace' => $this->available_on_integrated_marketplace,
            'extract_path' => $this->extract_path,
            'rename_extract_to' => $this->rename_extract_to,
            'size' => $this->size,
            'checksum' => $this->checksum,
        ];
    }
}
