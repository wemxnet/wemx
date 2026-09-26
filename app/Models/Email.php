<?php

namespace App\Models;

use App\Actions\EmailActions;
use App\Jobs\DeliverCustomerMail;
use App\Mail\EmailTheme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Email extends Model
{
    protected $fillable = [
        'user_id',
        'mailable_type',
        'mailable_id',
        'token',
        'identifier',
        'from',
        'to',
        'subject',
        'lines',
        'table',
        'button_text',
        'button_url',
        'attachments',
        'theme',
        'display',
        'status',
        'seen_at',
        'data',
    ];

    protected $casts = [
        'lines' => 'array',
        'table' => 'array',
        'attachments' => 'array',
        'display' => 'boolean',
        'seen_at' => 'datetime',
        'data' => 'array',
    ];

    protected $hidden = [
        'token',
    ];

    protected $attributes = [
        'status' => 'pending',
        'display' => true,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($email) {
            $email->from = config('mail.from.address');

            if (EmailTheme::find($email->theme) === null) {
                $email->theme = EmailTheme::default()->slug;
            }
        });

        static::created(function ($email) {
            DeliverCustomerMail::dispatch($email);
        });
    }

    public function mailable()
    {
        return $this->morphTo();
    }

    public function markAsDelivered(): void
    {
        $this->update(['status' => 'delivered']);
    }

    public function markAsSeen(): void
    {
        $this->update(['status' => 'read', 'seen_at' => now()]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function actions(): EmailActions
    {
        return new EmailActions;
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('subject', 'like', "%$search%")
            ->orWhere('from', 'like', "%$search%")
            ->orWhere('to', 'like', "%$search%");
    }
}
