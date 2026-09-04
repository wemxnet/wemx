<?php

namespace Extensions\Modules\Tickets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    public const TYPE_COMMENT = 'comment';

    public const TYPE_NOTE = 'note';

    public const TYPE_EVENT = 'event';

    protected $table = 'ticket_messages';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'type',
        'event_type',
        'is_staff',
        'from_admin',
        'body',
        'meta',
        'author_name',
        'author_email',
    ];

    protected $attributes = [
        'type' => self::TYPE_COMMENT,
        'is_staff' => false,
        'from_admin' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_staff' => 'boolean',
            'from_admin' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isComment(): bool
    {
        return $this->type === self::TYPE_COMMENT;
    }

    public function isNote(): bool
    {
        return $this->type === self::TYPE_NOTE;
    }

    public function isEvent(): bool
    {
        return $this->type === self::TYPE_EVENT;
    }

    public function isFromAdmin(): bool
    {
        return (bool) $this->from_admin;
    }

    public function isFromEmail(): bool
    {
        return ($this->meta['source'] ?? null) === 'email';
    }

    public function timelineIcon(): string
    {
        return match (true) {
            $this->isEvent() => 'adjustments',
            $this->isNote() => 'notes',
            default => 'message-circle',
        };
    }

    public function authorDisplayName(): string
    {
        if ($this->user) {
            return $this->user->full_name ?: $this->user->username;
        }

        return $this->author_name ?: ($this->author_email ?: 'Guest');
    }

    public function renderedBody(): string
    {
        return Ticket::renderMarkdown($this->body);
    }

    public function eventSummary(): string
    {
        $actor = $this->authorDisplayName();
        $meta = $this->meta ?? [];

        return match ($this->event_type) {
            'department_changed' => "{$actor} changed the department from {$meta['from']} to {$meta['to']}",
            'priority_changed' => "{$actor} changed the priority from {$meta['from']} to {$meta['to']}",
            'status_changed' => ($meta['reason'] ?? null) === 'inactivity'
                ? 'This ticket was automatically closed after '.($meta['days'] ?? 0).' days without a reply'
                : "{$actor} {$meta['action']} this ticket",
            'order_linked' => isset($meta['order_id'])
                ? "{$actor} linked order #{$meta['order_id']}"
                : "{$actor} unlinked the related order",
            'lock_changed' => "{$actor} {$meta['action']} this ticket",
            'member_added' => "{$actor} added {$meta['name']} to this ticket",
            'member_removed' => "{$actor} removed {$meta['name']} from this ticket",
            'subscribed' => "{$actor} subscribed to this ticket",
            'unsubscribed' => "{$actor} unsubscribed from this ticket",
            'assigned' => isset($meta['to'])
                ? "{$actor} assigned this ticket to {$meta['to']}"
                : "{$actor} unassigned this ticket",
            'title_changed' => "{$actor} renamed this ticket",
            default => $this->body ?: "{$actor} updated this ticket",
        };
    }

    public function scopeVisibleToClients(Builder $query): Builder
    {
        return $query->where('type', '!=', self::TYPE_NOTE);
    }
}
