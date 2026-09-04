<?php

namespace App\Models;

use App\Actions\SubscriptionActions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'token',
        'user_id',
        'gateway_config_id',
        'subscription_id',
        'subscribable_type',
        'subscribable_id',
        'status',
        'description',
        'currency',
        'amount',
        'frequency',
        'cancel_reason',
        'manage_url',
        'success_url',
        'cancel_url',
        'handler',
        'data',
        'gateway_data',
        'activated_at',
        'next_billing_at',
        'cancelled_at',
        'last_checked_at',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'data' => 'array',
        'gateway_data' => 'array',
        'activated_at' => 'datetime',
        'next_billing_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($subscription) {
            // if token is empty, generate a random token
            if (empty($subscription->token)) {
                $subscription->token = Str::random(32);
            }

            // if currency is empty, set it to the active currency
            if (empty($subscription->currency)) {
                $subscription->currency = settings('currency', 'USD');
            }

            // set activated_at to now, will later be updated when payment is confirmed
            if (empty($subscription->activated_at)) {
                $subscription->activated_at = now();
            }
        });
    }

    public static function actions()
    {
        return new SubscriptionActions;
    }

    public function gatewayConfig()
    {
        return $this->belongsTo(GatewayConfig::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscribable()
    {
        return $this->morphTo();
    }

    public function subscribeWith(GatewayConfig $gatewayConfig)
    {
        $activeCurrency = activeCurrency();

        if ($gatewayConfig->gateway->supportsCurrency($activeCurrency)) {
            $convertedTotals = $this->convertTotalsToCurrency($activeCurrency);
        } else {
            // as fallback, we convert to the gateways default currency
            $gatewayDefaultCurrency = $gatewayConfig->gateway->baseCurrency();
            $convertedTotals = $this->convertTotalsToCurrency($gatewayDefaultCurrency);
        }

        $this->update(array_merge($convertedTotals, [
            'gateway_config_id' => $gatewayConfig->id,
        ]));

        return $gatewayConfig->gateway->subscribe($this, $gatewayConfig);
    }

    public function cancelSubscription()
    {
        if ($this->isNotActive()) {
            return;
        }

        $gatewayConfig = $this->gatewayConfig;

        return $gatewayConfig->gateway->cancelSubscription($this, $gatewayConfig);
    }

    public function check()
    {
        $gatewayConfig = $this->gatewayConfig;

        // update last_checked_at
        $this->update([
            'last_checked_at' => now(),
        ]);

        return $gatewayConfig->gateway->checkSubscription($this, $gatewayConfig);
    }

    public function convertTotalsToCurrency(string $currency): array
    {
        return [
            'amount' => price($this->amount, to: $currency, in: $this->currency, absolute: true),
            'currency' => $currency,
        ];
    }

    public function data($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function isActive()
    {
        // if status is active, or if cancelled and next billing date is in the future
        return $this->status === 'active' || ($this->status === 'cancelled' && $this->next_billing_at && $this->next_billing_at->isFuture());
    }

    public function isNotActive()
    {
        return ! $this->isActive();
    }

    public function activated($subscriptionId, $nextBillingAt = null, $subscriptionData = []): void
    {
        if ($this->isActive()) {
            return;
        }

        // if next billing date is not provided, set it to now + frequency days
        if (! $nextBillingAt) {
            $nextBillingAt = now()->addDays($this->frequency);
        }

        // check if next billing date is a string and convert to Carbon instance, else throw exception
        if (is_string($nextBillingAt)) {
            $nextBillingAt = Carbon::parse($nextBillingAt);
        } elseif (! ($nextBillingAt instanceof Carbon)) {
            throw new \InvalidArgumentException('Next billing date must be a string or a Carbon instance.');
        }

        $this->update([
            'status' => 'active',
            'subscription_id' => $subscriptionId,
            'gateway_data' => $subscriptionData,
            'activated_at' => now(),
            'next_billing_at' => $nextBillingAt,
        ]);

        $this->callHandler('onSubscriptionActivated');

        // email subscription activated
        $this->emailSubscriptionActivation();
    }

    public function inactive()
    {
        if ($this->isNotActive()) {
            return;
        }

        $this->update([
            'status' => 'inactive',
            'last_checked_at' => now(),
        ]);

        $this->callHandler('onSubscriptionDeactivated');

        // email subscription inactive
        $this->emailSubscriptionInactive();
    }

    public function cancelled($reason = 'No reason provided')
    {
        if ($this->isNotActive()) {
            return;
        }

        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        $this->callHandler('onSubscriptionCancelled');

        // email subscription cancelled
        $this->emailSubscriptionCancellation();
    }

    public function updateNextBillingDate($nextBillingAt)
    {
        // check if next billing date is a string and convert to Carbon instance, else throw exception
        if (is_string($nextBillingAt)) {
            $nextBillingAt = Carbon::parse($nextBillingAt);
        } elseif (! ($nextBillingAt instanceof Carbon)) {
            throw new \InvalidArgumentException('Next billing date must be a string or a Carbon instance.');
        }

        $this->update([
            'next_billing_at' => $nextBillingAt,
            'last_checked_at' => now(),
        ]);

        $this->callHandler('onSubscriptionBillingDateUpdated');
    }

    public function callHandler($method)
    {
        if ($this->handler && method_exists($this->handler, $method)) {
            $handler = new $this->handler;
            $handler->$method($this);
        }
    }

    public function total()
    {
        return $this->amount;
    }

    public function emailSubscriptionActivation()
    {
        $this->sendSubscriptionEmail('subscription.activated');
    }

    public function emailSubscriptionCancellation()
    {
        $this->sendSubscriptionEmail('subscription.cancelled', [
            'active_until' => $this->next_billing_at
                ? 'Your subscription will remain active until '.$this->next_billing_at->toDayDateTimeString()
                : '',
        ]);
    }

    public function emailSubscriptionInactive()
    {
        $this->sendSubscriptionEmail('subscription.inactive');
    }

    /**
     * @return array<string, mixed>
     */
    public function subscriptionEmailVariables(): array
    {
        return [
            'description' => Str::limit($this->description, 50),
            'amount' => priceIn($this->total(), $this->currency).' / '.daysToPeriod($this->frequency),
            'gateway' => $this->gatewayConfig ? $this->gatewayConfig->display_name : 'N/A',
            'subscription_id' => Str::limit((string) $this->subscription_id, 32),
        ];
    }

    /**
     * @return array{columns: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public function subscriptionEmailTable(): array
    {
        $variables = $this->subscriptionEmailVariables();

        return [
            'columns' => [
                'Description',
                'Amount',
                'Gateway',
                'Subscription ID',
            ],
            'rows' => [
                [
                    $variables['description'],
                    $variables['amount'],
                    $variables['gateway'],
                    $variables['subscription_id'],
                ],
            ],
        ];
    }

    public function sendSubscriptionEmail(string $identifier, array $variables = []): void
    {
        if (! $this->user) {
            return;
        }

        $this->user->email([
            'identifier' => $identifier,
            'mailable_type' => self::class,
            'mailable_id' => $this->id,
            'variables' => array_merge($this->subscriptionEmailVariables(), $variables),
            'table' => $this->subscriptionEmailTable(),
            'button' => [
                'url' => route('subscriptions.index'),
            ],
        ]);
    }

    public function getSuccessUrlAttribute($value)
    {
        return $value ?: route('payments.completed');
    }

    public function getCancelUrlAttribute($value)
    {
        return $value ?: route('payments.cancelled');
    }

    public function successUrl()
    {
        return $this->success_url;
    }

    public function cancelUrl()
    {
        return $this->cancel_url;
    }

    public function callbackUrl($data = [])
    {
        $urlData = array_merge($data, [
            'webhook_id' => $this->gatewayConfig->webhook_id,
            'subscription_token' => $this->token,
        ]);

        return route('payments.gateway.callback', $urlData);
    }
}
