<?php

namespace Extensions\Modules\Tickets\Models;

use Extensions\Modules\Tickets\Actions\TicketDepartmentActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TicketDepartment extends Model
{
    protected $table = 'ticket_departments';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'allow_guest_tickets',
        'allow_guest_members',
        'allow_invites',
        'prefill_template',
        'auto_response',
        'notify_email',
        'auto_close_days',
        'sort_order',
    ];

    protected $attributes = [
        'is_active' => true,
        'allow_guest_tickets' => false,
        'allow_guest_members' => false,
        'allow_invites' => true,
        'auto_close_days' => 0,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allow_guest_tickets' => 'boolean',
            'allow_guest_members' => 'boolean',
            'allow_invites' => 'boolean',
            'auto_close_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public static function actions(): TicketDepartmentActions
    {
        return new TicketDepartmentActions;
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'department_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeAcceptsGuests(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('allow_guest_tickets', true);
    }

    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name) ?: 'department';
        $base = $slug;
        $i = 1;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
