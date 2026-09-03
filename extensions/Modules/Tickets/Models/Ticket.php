<?php

namespace Extensions\Modules\Tickets\Models;

use App\Models\Order;
use App\Models\User;
use Extensions\Modules\Tickets\Actions\TicketActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ticket extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const REPLY_CLIENT = 'client';

    public const REPLY_STAFF = 'staff';

    public const REPLY_SYSTEM = 'system';

    protected $table = 'tickets';

    protected $fillable = [
        'number',
        'department_id',
        'user_id',
        'order_id',
        'assigned_to',
        'title',
        'status',
        'priority',
        'last_reply_from',
        'last_replied_at',
        'closed_at',
        'closed_by',
        'locked_at',
        'locked_by',
        'guest_name',
        'guest_email',
        'token',
    ];

    protected $attributes = [
        'status' => self::STATUS_OPEN,
        'priority' => self::PRIORITY_MEDIUM,
        'last_reply_from' => self::REPLY_CLIENT,
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'last_replied_at' => 'datetime',
            'closed_at' => 'datetime',
            'locked_at' => 'datetime',
            'number' => 'integer',
        ];
    }

    public static function actions(): TicketActions
    {
        return new TicketActions;
    }

    public static function priorities(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_MEDIUM,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT,
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_CLOSED,
        ];
    }

    public function priorityBadgeClass(): string
    {
        return match ($this->priority) {
            self::PRIORITY_URGENT => 'bg-red-lt',
            self::PRIORITY_HIGH => 'bg-orange-lt',
            self::PRIORITY_MEDIUM => 'bg-blue-lt',
            default => 'bg-secondary-lt',
        };
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(TicketDepartment::class, 'department_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TicketMember::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isGuestTicket(): bool
    {
        return $this->user_id === null;
    }

    public function awaitingStaff(): bool
    {
        return $this->isOpen() && $this->last_reply_from !== self::REPLY_STAFF;
    }

    public function requesterName(): string
    {
        if ($this->user) {
            return $this->user->full_name ?: $this->user->username;
        }

        return $this->guest_name ?: ($this->guest_email ?: 'Guest');
    }

    public function requesterEmail(): ?string
    {
        return $this->user?->email ?? $this->guest_email;
    }

    public function displayNumber(): string
    {
        return '#'.$this->number;
    }

    public function clientUrl(): string
    {
        if ($this->user_id) {
            return route('tickets.view', $this);
        }

        return route('tickets.guest', $this->token);
    }

    public function adminUrl(): string
    {
        return route('admin.tickets.view', $this);
    }

    public function timeline(?User $viewer = null): Collection
    {
        $query = $this->messages()->with('user');

        if (! $viewer?->isStaff()) {
            $query->visibleToClients();
        }

        return $query->get();
    }

    public function memberForUser(?User $user, ?string $email = null): ?TicketMember
    {
        if ($user) {
            $member = $this->members->firstWhere('user_id', $user->id);

            if ($member) {
                return $member;
            }

            $email = $email ?: $user->email;
        }

        if ($email) {
            return $this->members->first(fn (TicketMember $member) => strcasecmp($member->email, $email) === 0);
        }

        return null;
    }

    public function isParticipant(?User $user, ?string $email = null): bool
    {
        return $this->memberForUser($user, $email) !== null;
    }

    public function canBeViewedBy(?User $user, ?string $token = null): bool
    {
        if ($user?->isStaff() && $user->hasPermission('admin.tickets.view')) {
            return true;
        }

        if ($user && $this->isParticipant($user)) {
            return true;
        }

        if ($token && hash_equals($this->token, $token)) {
            return true;
        }

        if ($token) {
            return $this->members->contains(fn (TicketMember $member) => hash_equals($member->access_token, $token));
        }

        return false;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    public function scopeAwaitingStaff(Builder $query): Builder
    {
        return $query->open()->where('last_reply_from', '!=', self::REPLY_STAFF);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $inner) use ($user) {
            $inner->where('user_id', $user->id)
                ->orWhereHas('members', fn (Builder $members) => $members->where('user_id', $user->id)->orWhere('email', $user->email));
        });
    }

    public function scopeOpenedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeOrderedForStaff(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE WHEN status = 'closed' THEN 1 ELSE 0 END")
            ->orderByRaw("CASE last_reply_from WHEN 'client' THEN 0 WHEN 'system' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderBy('last_replied_at');
    }

    public static function needingStaffReply(int $limit = 8): Collection
    {
        return static::query()
            ->with(['department', 'user'])
            ->awaitingStaff()
            ->orderedForStaff()
            ->limit($limit)
            ->get();
    }

    public static function openedByUser(User $user, int $limit = 20): Collection
    {
        return static::query()
            ->with(['department'])
            ->openedBy($user)
            ->orderedForStaff()
            ->limit($limit)
            ->get();
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! $search) {
            return $query;
        }

        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        return $query->where(function (Builder $inner) use ($term, $search) {
            $inner->where('title', 'like', $term)
                ->orWhere('guest_email', 'like', $term)
                ->orWhere('guest_name', 'like', $term)
                ->orWhere('number', $search);

            if (is_numeric($search)) {
                $inner->orWhere('id', (int) $search);
            }
        });
    }

    public static function nextNumber(): int
    {
        return (int) static::query()->lockForUpdate()->max('number') + 1;
    }

    public static function newToken(): string
    {
        return Str::random(64);
    }

    public static function renderMarkdown(?string $body): string
    {
        return Str::markdown($body ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
