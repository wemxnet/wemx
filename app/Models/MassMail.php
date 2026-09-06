<?php

namespace App\Models;

use App\Actions\MassMailActions;
use App\Facades\World;
use Database\Factories\MassMailFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

class MassMail extends Model
{
    /** @use HasFactory<MassMailFactory> */
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const AUDIENCE_ALL_CUSTOMERS = 'all_customers';

    public const AUDIENCE_SUBSCRIBED = 'subscribed';

    public const AUDIENCE_WITH_ORDERS = 'with_orders';

    public const AUDIENCE_WITHOUT_ORDERS = 'without_orders';

    public const AUDIENCE_WITH_PACKAGE = 'with_package';

    public const AUDIENCE_WITH_ORDER_STATUS = 'with_order_status';

    public const AUDIENCE_USER_STATUS = 'user_status';

    public const AUDIENCE_UNPAID_INVOICES = 'unpaid_invoices';

    public const AUDIENCE_WITH_SUBSCRIPTION = 'with_subscription';

    public const AUDIENCE_BY_COUNTRY = 'by_country';

    public const AUDIENCE_TYPES = [
        self::AUDIENCE_ALL_CUSTOMERS,
        self::AUDIENCE_SUBSCRIBED,
        self::AUDIENCE_WITH_ORDERS,
        self::AUDIENCE_WITHOUT_ORDERS,
        self::AUDIENCE_WITH_PACKAGE,
        self::AUDIENCE_WITH_ORDER_STATUS,
        self::AUDIENCE_USER_STATUS,
        self::AUDIENCE_UNPAID_INVOICES,
        self::AUDIENCE_WITH_SUBSCRIPTION,
        self::AUDIENCE_BY_COUNTRY,
    ];

    public const ORDER_STATUSES = [
        'pending',
        'processing',
        'active',
        'suspended',
        'terminated',
    ];

    public const USER_STATUSES = [
        'active',
        'pending',
        'suspended',
    ];

    protected $fillable = [
        'created_by',
        'subject',
        'body',
        'button_text',
        'button_url',
        'audience_type',
        'filters',
        'status',
        'recipient_count',
        'sent_count',
        'failed_count',
        'last_user_id',
        'scheduled_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'last_error',
    ];

    protected $attributes = [
        'status' => self::STATUS_QUEUED,
        'recipient_count' => 0,
        'sent_count' => 0,
        'failed_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function actions(): MassMailActions
    {
        return new MassMailActions;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function emails(): MorphMany
    {
        return $this->morphMany(Email::class, 'mailable');
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function audienceOptions(): array
    {
        return [
            self::AUDIENCE_ALL_CUSTOMERS => [
                'label' => __('messages.mass_mail_audience_all_customers'),
                'description' => __('messages.mass_mail_audience_all_customers_desc'),
            ],
            self::AUDIENCE_SUBSCRIBED => [
                'label' => __('messages.mass_mail_audience_subscribed'),
                'description' => __('messages.mass_mail_audience_subscribed_desc'),
            ],
            self::AUDIENCE_WITH_ORDERS => [
                'label' => __('messages.mass_mail_audience_with_orders'),
                'description' => __('messages.mass_mail_audience_with_orders_desc'),
            ],
            self::AUDIENCE_WITHOUT_ORDERS => [
                'label' => __('messages.mass_mail_audience_without_orders'),
                'description' => __('messages.mass_mail_audience_without_orders_desc'),
            ],
            self::AUDIENCE_WITH_PACKAGE => [
                'label' => __('messages.mass_mail_audience_with_package'),
                'description' => __('messages.mass_mail_audience_with_package_desc'),
            ],
            self::AUDIENCE_WITH_ORDER_STATUS => [
                'label' => __('messages.mass_mail_audience_with_order_status'),
                'description' => __('messages.mass_mail_audience_with_order_status_desc'),
            ],
            self::AUDIENCE_USER_STATUS => [
                'label' => __('messages.mass_mail_audience_user_status'),
                'description' => __('messages.mass_mail_audience_user_status_desc'),
            ],
            self::AUDIENCE_UNPAID_INVOICES => [
                'label' => __('messages.mass_mail_audience_unpaid_invoices'),
                'description' => __('messages.mass_mail_audience_unpaid_invoices_desc'),
            ],
            self::AUDIENCE_WITH_SUBSCRIPTION => [
                'label' => __('messages.mass_mail_audience_with_subscription'),
                'description' => __('messages.mass_mail_audience_with_subscription_desc'),
            ],
            self::AUDIENCE_BY_COUNTRY => [
                'label' => __('messages.mass_mail_audience_by_country'),
                'description' => __('messages.mass_mail_audience_by_country_desc'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function customersQuery(string $audienceType, array $filters = []): Builder
    {
        $query = User::query()
            ->where('users.id', '!=', 1)
            ->whereDoesntHave('roles')
            ->whereNotNull('users.email')
            ->where('users.email', '!=', '');

        $packageId = isset($filters['package_id']) && filled($filters['package_id'])
            ? (int) $filters['package_id']
            : null;
        $orderStatus = isset($filters['order_status']) && filled($filters['order_status'])
            ? (string) $filters['order_status']
            : null;
        $userStatus = isset($filters['user_status']) && filled($filters['user_status'])
            ? (string) $filters['user_status']
            : null;
        $country = isset($filters['country']) && filled($filters['country'])
            ? (string) $filters['country']
            : null;

        match ($audienceType) {
            self::AUDIENCE_SUBSCRIBED => $query->where('is_subscribed', true),
            self::AUDIENCE_WITH_ORDERS => $query->whereHas('orders'),
            self::AUDIENCE_WITHOUT_ORDERS => $query->whereDoesntHave('orders'),
            self::AUDIENCE_WITH_PACKAGE => $query->whereHas('orders', function (Builder $orders) use ($packageId, $orderStatus): void {
                $orders->where('package_id', $packageId);

                if ($orderStatus !== null) {
                    $orders->where('status', $orderStatus);
                }
            }),
            self::AUDIENCE_WITH_ORDER_STATUS => $query->whereHas('orders', function (Builder $orders) use ($orderStatus): void {
                $orders->where('status', $orderStatus);
            }),
            self::AUDIENCE_USER_STATUS => $query->where('status', $userStatus),
            self::AUDIENCE_UNPAID_INVOICES => $query->whereHas('payments', function (Builder $payments): void {
                $payments->where('status', 'unpaid');
            }),
            self::AUDIENCE_WITH_SUBSCRIPTION => $query->whereHas('subscriptions'),
            self::AUDIENCE_BY_COUNTRY => $query->where('country', $country),
            default => null,
        };

        if ($audienceType !== self::AUDIENCE_USER_STATUS && $userStatus !== null) {
            $query->where('status', $userStatus);
        }

        if ($audienceType !== self::AUDIENCE_SUBSCRIBED && ($filters['subscribed_only'] ?? false)) {
            $query->where('is_subscribed', true);
        }

        if ($filters['verified_only'] ?? false) {
            $query->whereNotNull('email_verified_at');
        }

        if ($audienceType !== self::AUDIENCE_BY_COUNTRY && $country !== null) {
            $query->where('country', $country);
        }

        return $query;
    }

    public function audienceQuery(): Builder
    {
        return self::customersQuery($this->audience_type, $this->filters ?? []);
    }

    public function audienceCount(): int
    {
        return $this->audienceQuery()->count();
    }

    /**
     * @return Collection<int, User>
     */
    public function sampleRecipients(int $limit = 8)
    {
        return $this->audienceQuery()
            ->orderBy('users.id')
            ->limit($limit)
            ->get(['users.id', 'users.first_name', 'users.last_name', 'users.username', 'users.email']);
    }

    public function nextRecipients(int $limit): \Illuminate\Database\Eloquent\Collection
    {
        return $this->audienceQuery()
            ->where('users.id', '>', $this->last_user_id ?? 0)
            ->orderBy('users.id')
            ->limit($limit)
            ->get();
    }

    public function isDue(): bool
    {
        if (! in_array($this->status, [self::STATUS_QUEUED, self::STATUS_SENDING], true)) {
            return false;
        }

        return $this->scheduled_at === null || $this->scheduled_at->lte(now());
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_SENDING], true);
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_SENDING], true);
    }

    public function audienceLabel(): string
    {
        return self::audienceOptions()[$this->audience_type]['label'] ?? $this->audience_type;
    }

    /**
     * @return array<int, string>
     */
    public function audienceSummary(): array
    {
        $filters = $this->filters ?? [];
        $parts = [$this->audienceLabel()];

        if (! empty($filters['package_id'])) {
            $package = Package::query()->find($filters['package_id']);
            $parts[] = __('messages.package').': '.($package?->name ?? '#'.$filters['package_id']);
        }

        if (! empty($filters['order_status'])) {
            $parts[] = __('messages.order').' '.__('messages.status').': '.ucfirst((string) $filters['order_status']);
        }

        if (! empty($filters['user_status'])) {
            $parts[] = __('messages.user').' '.__('messages.status').': '.ucfirst((string) $filters['user_status']);
        }

        if (! empty($filters['country'])) {
            $parts[] = World::countryName($filters['country']);
        }

        if (! empty($filters['subscribed_only'])) {
            $parts[] = __('messages.mass_mail_subscribed_only');
        }

        if (! empty($filters['verified_only'])) {
            $parts[] = __('messages.mass_mail_verified_only');
        }

        return $parts;
    }

    /**
     * @return array<string, string>
     */
    public static function placeholderVariables(?User $user = null): array
    {
        return [
            'app_name' => settings('app_name', 'My Application'),
            'user_name' => $user?->first_name ?: ($user?->username ?? 'Alex'),
            'user_username' => $user?->username ?? 'alex',
            'user_email' => $user?->email ?? 'alex@example.com',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function placeholderHints(): array
    {
        return [
            'app_name' => __('messages.mass_mail_placeholder_app_name'),
            'user_name' => __('messages.mass_mail_placeholder_user_name'),
            'user_username' => __('messages.mass_mail_placeholder_user_username'),
            'user_email' => __('messages.mass_mail_placeholder_user_email'),
        ];
    }
}
