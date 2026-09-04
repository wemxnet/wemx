<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Actions\AuthActions;
use App\Actions\UserActions;
use App\Events;
use App\Traits\Models\HasRoles;
use App\Traits\Models\HasSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class User extends Authenticatable
{
    use HasFactory, HasRoles, HasSettings;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'first_name',
        'last_name',
        'status',
        'password',
        'language',
        'avatar',
        'phone',
        'is_subscribed',
        'country',
        'data',
        'tfa_enabled',
        'tfa_secret',
        'email_verified_at',
        'last_seen_at',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'tfa_secret',
        'remember_token',
        'verification_token',
        'avatar',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_login_at' => 'datetime',
            'balance' => 'decimal:8',
            'data' => 'array',
            'is_subscribed' => 'boolean',
            'tfa_enabled' => 'boolean',
        ];
    }

    protected $dispatchesEvents = [
        'created' => Events\Users\UserCreated::class,
        'deleted' => Events\Users\UserDeleted::class,
        'updated' => Events\Users\UserUpdated::class,
    ];

    protected static function booted()
    {
        static::created(function ($user) {
            $user->createEmptyAddress();
        });

        static::updating(function ($user) {
            // Log the changes made to the user model
            self::logUserUpdates($user);
        });
    }

    public function isPrimaryAdmin(): bool
    {
        return $this->id === 1;
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function getInitialsAttribute(): string
    {
        return substr($this->first_name, 0, 1).substr($this->last_name, 0, 1);
    }

    public function getLanguageAttribute($value): string
    {
        // if the language is not set, return the default language
        if (! $value) {
            return settings('language', 'en');
        }

        // ensure the language is in the list of available languages
        if (! array_key_exists($value, config('languages.languages'))) {
            return settings('language', 'en');
        }

        return $value;
    }

    public function language(): object
    {
        return (object) config('languages.languages')[$this->language];
    }

    public static function authActions(): AuthActions
    {
        return new AuthActions;
    }

    public static function actions(): UserActions
    {
        return new UserActions;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function bans(): HasMany
    {
        return $this->hasMany(UserBan::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    public function adminNotes()
    {
        return $this->morphMany(AdminNote::class, 'notable');
    }

    public function hasCompletedAddress(): bool
    {
        $address = $this->address;

        if (! $address) {
            $address = $this->createEmptyAddress();
        }

        // Check if required fields are filled
        $requiredFields = ['address', 'country', 'city', 'region', 'zip_code'];
        foreach ($requiredFields as $field) {
            if (empty($address->$field)) {
                return false;
            }
        }

        return true;
    }

    public function scopeSearch($query, string $search): void
    {
        if ($search) {
            $query->where('username', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%');
        }
    }

    public function isOnline(int $minutes = 5): bool
    {
        return $this->last_seen_at && $this->last_seen_at->isAfter(now()->subMinutes($minutes));
    }

    public function hasActiveBan(): bool
    {
        return $this->bans()
            ->whereNull('lifted_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function hasBanHistory(): bool
    {
        return $this->bans()->exists();
    }

    public function activeBan(): ?UserBan
    {
        return $this->bans()
            ->whereNull('lifted_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();
    }

    public function lastSeenNow(): void
    {
        DB::table('users')->where('id', $this->id)->update(['last_seen_at' => now()]);
    }

    public function getActivityLogCountForField(string $field)
    {
        return $this->activityLogs()
            ->where('field', $field)
            ->count();
    }

    public function getAvatarUrl(): string
    {
        if ($this->avatar) {
            return $this->avatar;
        }

        return 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($this->email)));
    }

    public function email(array $data): void
    {
        Email::actions()->sendUserEmail(array_filter([
            'user_id' => $this->id,
            'token' => $data['token'] ?? null,
            'identifier' => $data['identifier'] ?? null,
            'template' => $data['template'] ?? null,
            'variables' => $data['variables'] ?? null,
            'mailable_type' => $data['mailable_type'] ?? null,
            'mailable_id' => $data['mailable_id'] ?? null,
            'subject' => $data['subject'] ?? null,
            'lines' => $data['lines'] ?? null,
            'table' => $data['table'] ?? null,
            'button_text' => $data['button']['text'] ?? null,
            'button_url' => $data['button']['url'] ?? null,
            'attachments' => $data['attachments'] ?? null,
            'theme' => $data['theme'] ?? null,
            'display' => $data['display'] ?? null,
            'data' => $data['data'] ?? null,
        ], fn ($value) => $value !== null));
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function address(): HasOne
    {
        return $this->hasOne(Address::class);
    }

    public function balanceTransactions(): HasMany
    {
        return $this->hasMany(BalanceTransaction::class);
    }

    public function updateBalance($type = '+', $amount = 0, $description = null)
    {
        if (! in_array($type, ['+', '-', '='])) {
            throw new \InvalidArgumentException('Invalid balance update type. Use "+", "-", or "=".');
        }

        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount must be a positive value.');
        }

        $currentBalance = $this->balance ?? 0;

        if ($type === '+') {
            $newBalance = $currentBalance + $amount;
        } elseif ($type === '-') {
            if ($currentBalance < $amount) {
                throw new \InvalidArgumentException('Insufficient balance for this operation.');
            }
            $newBalance = $currentBalance - $amount;
        } else { // '='
            $newBalance = $amount;
        }

        // Update user's balance
        $this->balance = $newBalance;
        $this->save();

        // Log the balance transaction
        $this->balanceTransactions()->create([
            'result' => $type,
            'description' => $description,
            'amount' => $amount,
            'balance_before_transaction' => $currentBalance,
        ]);
    }

    public function createEmptyAddress()
    {
        // check if user doesnt have address, create an empty one
        if (! $this->address) {
            $address = new Address;
            $address->user_id = $this->id;
            $address->save();

            return $address;
        }

        return $this->address;
    }

    public function emailVerificationToken(): void
    {
        if (! $this->verification_token) {
            $this->verification_token = Str::random(60);
            $this->verification_token_sent_at = now();
            $this->save();
        } else {
            $this->verification_token_sent_at = now();
            $this->save();
        }

        $this->email([
            'identifier' => 'email_verification',
            'button' => [
                'url' => route('verify-email.token', ['token' => $this->verification_token]),
            ],
        ]);
    }

    public function emailPasswordResetLink(): void
    {
        $newToken = Str::random(64);

        $token = PasswordResetToken::updateOrCreate(
            ['email' => $this->email],
            [
                'token' => $newToken,
                'created_at' => now(),
            ]
        );

        $this->email([
            'identifier' => 'password_reset',
            'button' => [
                'url' => route('reset-password', ['token' => $newToken]),
            ],
        ]);
    }

    public function generateTwoFactorSecret(): string
    {
        if ($this->tfa_secret) {
            return $this->tfa_secret;
        }

        // Generate a new secret key for the user
        $google2fa = new Google2FA;
        $secretKey = $google2fa->generateSecretKey();

        // Save the secret key to the user's record
        $this->tfa_secret = $secretKey;
        $this->save();

        return $secretKey;
    }

    public function verifyTfaCode(string $code): bool
    {
        if (! $this->tfa_secret) {
            return false;
        }

        $google2fa = new Google2FA;

        return $google2fa->verifyKey($this->tfa_secret, $code);
    }

    /**
     * This method logs an activity for the user.
     *
     *
     * @return ActivityLog
     */
    public function logActivity(array $data)
    {
        return ActivityLog::create($data);
    }

    /**
     * This method retrieves the activity logs for the user.
     */
    public function activityLogs()
    {
        // return from morps to many relationship
        return $this->morphMany(ActivityLog::class, 'model')
            ->orderBy('created_at', 'desc');
    }

    /**
     * This method logs the changes made to the user model.
     * It checks if the user is dirty (i.e., has unsaved changes)
     * and if so, it logs the changes made to the specified fields.
     *
     * @param  User  $user
     */
    public static function logUserUpdates($user): void
    {
        $fieldsToLog = ['first_name', 'last_name', 'username', 'email', 'password', 'status', 'balance'];

        foreach ($fieldsToLog as $field) {
            if ($user->isDirty($field)) {
                $causer = auth()->user() ?? $user;
                $oldValue = $user->getOriginal($field);
                $newValue = $user->$field;

                // Hide actual password values in logs
                if ($field === 'password') {
                    $oldValue = $newValue = '********';
                }

                // Log the change
                $user->logActivity([
                    'user_id' => $causer->id,
                    'event' => "user.updated.{$field}",
                    'description' => "User {$field} updated by {$causer->username}",
                    'field' => $field,
                    'model_type' => User::class,
                    'model_id' => $user->id,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ]);
            }
        }
    }
}
