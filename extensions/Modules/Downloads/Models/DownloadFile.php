<?php

namespace Extensions\Modules\Downloads\Models;

use App\Models\Package;
use App\Models\User;
use Extensions\Modules\Downloads\Actions\DownloadFileActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class DownloadFile extends Model
{
    public const DENIAL_LOGIN = 'login';

    public const DENIAL_PACKAGE = 'package';

    public const DENIAL_SERVICE = 'service';

    public const DENIAL_UNAVAILABLE = 'unavailable';

    public const DENIAL_EXPIRED = 'expired';

    public const DENIAL_LIMIT = 'limit';

    public const DENIAL_UNPUBLISHED = 'unpublished';

    protected $table = 'download_files';

    protected $fillable = [
        'folder_id',
        'uploaded_by',
        'name',
        'description',
        'version',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'is_published',
        'allow_guests',
        'require_any_order',
        'require_active_order',
        'hidden_until_eligible',
        'package_ids',
        'download_limit',
        'available_from',
        'available_until',
        'sort_order',
        'download_count',
    ];

    protected $attributes = [
        'disk' => 'local',
        'is_published' => true,
        'allow_guests' => false,
        'require_any_order' => false,
        'require_active_order' => true,
        'hidden_until_eligible' => false,
        'sort_order' => 0,
        'download_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_published' => 'boolean',
            'allow_guests' => 'boolean',
            'require_any_order' => 'boolean',
            'require_active_order' => 'boolean',
            'hidden_until_eligible' => 'boolean',
            'package_ids' => 'array',
            'download_limit' => 'integer',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'sort_order' => 'integer',
            'download_count' => 'integer',
        ];
    }

    public static function actions(): DownloadFileActions
    {
        return new DownloadFileActions;
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DownloadFolder::class, 'folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DownloadLog::class, 'file_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return list<int>
     */
    public function requiredPackageIds(): array
    {
        return collect($this->package_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function requiredPackages(): Collection
    {
        $ids = $this->requiredPackageIds();

        if ($ids === []) {
            return collect();
        }

        return Package::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    public function formattedSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }

    public function renderedDescription(): string
    {
        return Str::markdown($this->description ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function isWithinAvailabilityWindow(?\DateTimeInterface $at = null): bool
    {
        $at = $at ? Carbon::parse($at) : now();

        if ($this->available_from && $at->lt($this->available_from)) {
            return false;
        }

        if ($this->available_until && $at->gt($this->available_until)) {
            return false;
        }

        return true;
    }

    public function availabilityDenial(?\DateTimeInterface $at = null): ?string
    {
        $at = $at ? Carbon::parse($at) : now();

        if ($this->available_from && $at->lt($this->available_from)) {
            return self::DENIAL_UNAVAILABLE;
        }

        if ($this->available_until && $at->gt($this->available_until)) {
            return self::DENIAL_EXPIRED;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function eligibleOrderStatuses(): array
    {
        if ($this->require_active_order) {
            return ['active'];
        }

        return ['pending', 'processing', 'active', 'suspended'];
    }

    public function userHasEligibleOrder(?User $user, ?array $packageIds = null): bool
    {
        if (! $user) {
            return false;
        }

        return $user->orders()
            ->whereIn('status', $this->eligibleOrderStatuses())
            ->when($packageIds, fn (Builder $query) => $query->whereIn('package_id', $packageIds))
            ->exists();
    }

    public function remainingDownloadsFor(?User $user, ?string $ip = null): ?int
    {
        if ($this->download_limit === null) {
            return null;
        }

        $used = $this->logs()
            ->when($user, fn (Builder $query) => $query->where('user_id', $user->id))
            ->when(! $user, fn (Builder $query) => $query->where('ip_address', $ip))
            ->count();

        return max(0, $this->download_limit - $used);
    }

    public function staffCanManage(?User $user): bool
    {
        return (bool) $user?->isStaff() && $user->hasPermission('admin.downloads');
    }

    public function denialReason(?User $user, ?string $ip = null): ?string
    {
        if ($this->staffCanManage($user)) {
            return null;
        }

        if (! $this->is_published) {
            return self::DENIAL_UNPUBLISHED;
        }

        if ($availability = $this->availabilityDenial()) {
            return $availability;
        }

        $packageIds = $this->requiredPackageIds();
        $needsAccount = ! $this->allow_guests || $packageIds !== [] || $this->require_any_order;

        if ($needsAccount && ! $user) {
            return self::DENIAL_LOGIN;
        }

        if ($packageIds !== [] && ! $this->userHasEligibleOrder($user, $packageIds)) {
            return self::DENIAL_PACKAGE;
        }

        if ($packageIds === [] && $this->require_any_order && ! $this->userHasEligibleOrder($user)) {
            return self::DENIAL_SERVICE;
        }

        $remaining = $this->remainingDownloadsFor($user, $ip);

        if ($remaining !== null && $remaining <= 0) {
            return self::DENIAL_LIMIT;
        }

        return null;
    }

    public function canBeDownloadedBy(?User $user, ?string $ip = null): bool
    {
        return $this->denialReason($user, $ip) === null;
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($this->staffCanManage($user)) {
            return true;
        }

        if (! $this->is_published) {
            return false;
        }

        if (! $this->hidden_until_eligible) {
            return true;
        }

        return $this->canBeDownloadedBy($user);
    }

    public function denialLabel(?User $user, ?string $ip = null): ?string
    {
        return match ($this->denialReason($user, $ip)) {
            self::DENIAL_LOGIN => 'Sign in to download',
            self::DENIAL_PACKAGE => $this->packageRequirementLabel(),
            self::DENIAL_SERVICE => 'Requires an active service',
            self::DENIAL_UNAVAILABLE => 'Available '.$this->available_from?->diffForHumans(),
            self::DENIAL_EXPIRED => 'No longer available',
            self::DENIAL_LIMIT => 'Download limit reached',
            self::DENIAL_UNPUBLISHED => 'Not published',
            default => null,
        };
    }

    public function packageRequirementLabel(): string
    {
        $names = $this->requiredPackages()->pluck('name')->filter()->values();

        if ($names->isEmpty()) {
            return 'Requires a specific package';
        }

        if ($names->count() === 1) {
            return 'Requires '.$names->first();
        }

        return 'Requires one of: '.$names->join(', ');
    }

    public function accessSummary(): string
    {
        if ($this->requiredPackageIds() !== []) {
            return $this->packageRequirementLabel();
        }

        if ($this->require_any_order) {
            return 'Customers with a service';
        }

        if ($this->allow_guests) {
            return 'Guests and customers';
        }

        return 'Signed-in customers';
    }

    public function downloadName(): string
    {
        $original = $this->original_name ?: $this->name;
        $extension = pathinfo($original, PATHINFO_EXTENSION);

        if ($extension === '') {
            return $original;
        }

        $base = pathinfo($this->name, PATHINFO_FILENAME) ?: pathinfo($original, PATHINFO_FILENAME);

        return $base.'.'.$extension;
    }

    public static function nextSortOrder(int $folderId): int
    {
        return (int) static::query()->where('folder_id', $folderId)->max('sort_order') + 10;
    }
}
