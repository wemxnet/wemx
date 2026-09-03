<?php

namespace Extensions\Modules\Tickets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TicketMember extends Model
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_MEMBER = 'member';

    public const ROLE_STAFF = 'staff';

    protected $table = 'ticket_members';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'invited_by',
        'email',
        'name',
        'role',
        'is_subscribed',
        'access_token',
        'last_read_at',
    ];

    protected $attributes = [
        'role' => self::ROLE_MEMBER,
        'is_subscribed' => true,
    ];

    protected $hidden = [
        'access_token',
    ];

    protected function casts(): array
    {
        return [
            'is_subscribed' => 'boolean',
            'last_read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TicketMember $member) {
            $member->email = Str::lower($member->email);
            $member->access_token ??= Str::random(64);
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function displayName(): string
    {
        if ($this->user) {
            return $this->user->full_name ?: $this->user->username;
        }

        return $this->name ?: $this->email;
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function accessUrl(): string
    {
        if ($this->user_id) {
            return route('tickets.view', $this->ticket_id);
        }

        return route('tickets.guest', $this->ticket->token).'?member='.$this->access_token;
    }
}
