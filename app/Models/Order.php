<?php

namespace App\Models;

use App\Actions\OrderActions;
use App\Events;
use App\Extensions\Foundation\ExtensionFoundation;
use App\Jobs\Orders\OrderCreateServer;
use App\Jobs\Orders\OrderSuspendServer;
use App\Jobs\Orders\OrderTerminateServer;
use App\Jobs\Orders\OrderUnsuspendServer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'user_id',
        'package_id',
        'package_price_id',
        'external_id',
        'status',
        'cycle_price',
        'setup_fee',
        'upgrade_fee',
        'period_in_days',
        'due_date',
        'last_renewed_at',
        'auto_balance_renew',
        'data',
    ];

    protected function casts()
    {
        return [
            'due_date' => 'datetime',
            'last_renewed_at' => 'datetime',
            'cycle_price' => 'decimal:8',
            'setup_fee' => 'decimal:8',
            'upgrade_fee' => 'decimal:8',
            'auto_balance_renew' => 'boolean',
            'data' => 'array',
        ];
    }

    protected $dispatchesEvents = [
        'created' => Events\Orders\OrderCreated::class,
        'deleted' => Events\Orders\OrderDeleted::class,
        'updated' => Events\Orders\OrderUpdated::class,
    ];

    protected static function booted()
    {
        static::updating(function ($order) {
            // Log the changes made to the order model
            self::logOrderUpdates($order);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function orderSubscriptions()
    {
        return $this->hasMany(OrderSubscription::class);
    }

    public function logs()
    {
        return $this->hasMany(OrderLog::class);
    }

    public function exceptions()
    {
        return $this->hasMany(OrderExceptionLog::class);
    }

    public function log($data)
    {
        return $this->logs()->create($data);
    }

    public function prices()
    {
        return $this->hasMany(OrderPrice::class);
    }

    public function members()
    {
        return $this->hasMany(OrderMember::class);
    }

    public function isTerminated(): bool
    {
        return $this->status === 'terminated';
    }

    public function hasActiveSubscription($strict = false)
    {
        return $this->orderSubscriptions()->where('status', 'active')->when($strict, function ($query) {
            // check on subscription relationship if the subscription is also active
            $query->whereHas('subscription', function ($q) {
                $q->whereNull('cancelled_at');
            });
        })->first();
    }

    public function serverMethods(): ExtensionFoundation
    {
        return $this->package->serverConnection->server->extension();
    }

    public static function actions(): OrderActions
    {
        return new OrderActions;
    }

    public static function getOrdersPastDueDate($days = 3): Builder
    {
        return Order::query()
            ->whereNotNull('due_date')
            ->where('period_in_days', '!=', 0) // one time orders are not included
            ->where('due_date', '<', now()->subDays($days));
    }

    public static function getOrdersAboutToExpire(int $days = 3): Builder
    {
        return Order::query()
            ->where('status', 'active')
            ->whereNotNull('due_date')
            ->where('period_in_days', '!=', 0)
            ->whereBetween('due_date', [now(), now()->addDays($days)]);
    }

    public function hasEnoughBalanceToRenew(): bool
    {
        $balance = $this->user->balance;

        if ($balance < $this->price) {
            return false;
        }

        return true;
    }

    public function attemptBalanceRenewal(): bool
    {
        if (! $this->hasEnoughBalanceToRenew()) {
            throw new \Exception('User does not have enough balance to renew the order.');
        }

        $user = $this->user;

        $user->updateBalance(
            '-',
            $this->price,
            "Auto-renewal of order #{$this->id}",
        );

        $this->update([
            'due_date' => ($this->due_date ?? now())->addDays($this->period_in_days),
            'last_renewed_at' => now(),
        ]);

        $balanceGateway = GatewayConfig::balanceGateway();

        if ($balanceGateway) {
            $payment = $this->payments()->create([
                'user_id' => $this->user->id,
                'gateway_config_id' => $balanceGateway->id,
                'description' => "Auto-renewal of order #{$this->id}",
                'subtotal' => $this->price,
                'total' => $this->price,
                'earnings' => $this->price,
                'currency' => baseCurrency(),
                'status' => 'paid',
                'paid_at' => now(),
                'transaction_id' => 'BALANCE-'.strtoupper(uniqid()),
            ]);

            $payment->emailPaymentSuccess();
        }

        $this->emailAutoBalanceRenewal();

        return true;
    }

    public function createExternalUser(array $data)
    {
        ServerAccount::create([
            'user_id' => $this->user->id,
            'order_id' => $this->id,
            'server' => $this->package->serverConnection->extension_identifier,
            'external_id' => $data['external_id'] ?? null,
            'username' => $data['username'] ?? null,
            'password' => $data['password'] ?? null,
            'data' => $data['data'] ?? null,
        ]);
    }

    public function getExternalUser()
    {
        $user = ServerAccount::where('order_id', $this->id)->where('server', $this->package->serverConnection->extension_identifier)->first();

        if (! $user) {
            $user = ServerAccount::where('user_id', $this->user->id)->where('server', $this->package->serverConnection->extension_identifier)->first();
        }

        return $user ?? null;
    }

    public function hasExternalUser(): bool
    {
        if (! $this->getExternalUser()) {
            return false;
        }

        return true;
    }

    public function updateExternalPassword($password)
    {
        $this->getExternalUser()->update(['password' => $password]);
    }

    public function canChangeExternalPassword(): bool
    {
        return $this->package->serverConnection->server->functions()->canChangePassword();
    }

    // add price attribute that returns the total price of the order
    public function getPriceAttribute(): float|int
    {
        if ($this->isNotRecurring()) {
            return $this->prices->where('is_active', true)->sum('cycle_price') + $this->cycle_price + $this->setup_fee;
        }

        $dailyTotal = $this->prices->where('is_active', true)->sum('cycle_price') + $this->cycle_price;

        return $dailyTotal * $this->period_in_days;
    }

    public function getDailyPriceAttribute(): float|int
    {
        return $this->prices->where('is_active', true)->sum('cycle_price') + $this->cycle_price;
    }

    // add upgradeFee attribute that returns the total upgrade fee of the order
    public function getUpgradeFeeAttribute(): float|int
    {
        return $this->prices->where('is_active', true)->sum('upgrade_fee') + $this->attributes['upgrade_fee'];
    }

    // add isRecurring method that returns true if the order is recurring
    public function isRecurring(): bool
    {
        return $this->period_in_days > 0;
    }

    public function isNotRecurring(): bool
    {
        return ! $this->isRecurring();
    }

    // add interval method that returns the recurring interval of the order
    public function cycle(): string
    {
        return daysToPeriod($this->period_in_days);
    }

    public function emails()
    {
        return $this->morphMany(Email::class, 'mailable');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isNotActive(): bool
    {
        return ! $this->isActive();
    }

    public function option(string $key, mixed $default = null): mixed
    {
        // 1) Exact match (e.g. "environment.MINECRAFT_VERSION")
        if ($price = $this->prices->firstWhere('key', $key)) {
            return $price->value;
        }

        // 2) Children of the prefix (e.g. keys like "environment.*")
        $children = $this->prices->filter(function ($price) use ($key) {
            return Str::startsWith($price->key, $key.'.');
        });

        if ($children->isNotEmpty()) {
            $result = [];

            foreach ($children as $price) {
                // e.g. "environment.MINECRAFT_VERSION" -> "MINECRAFT_VERSION"
                // e.g. "environment.auth.user" -> "auth.user"
                $suffix = Str::after($price->key, $key.'.');

                // Build nested structure if the suffix itself has dots
                Arr::set($result, $suffix, $price->value);
            }

            return $result;
        }

        // 3) Fallback
        return $this->package->data($key, $default);
    }

    public function createServer($dispatch = true)
    {
        if ($dispatch) {
            OrderCreateServer::dispatch($this);

            return;
        }

        OrderCreateServer::dispatchSync($this);
    }

    public function suspendServer($dispatch = true)
    {
        if ($dispatch) {
            OrderSuspendServer::dispatch($this);

            return;
        }

        OrderSuspendServer::dispatchSync($this);
    }

    public function unsuspendServer($dispatch = true)
    {
        if ($dispatch) {
            OrderUnsuspendServer::dispatch($this);

            return;
        }

        OrderUnsuspendServer::dispatchSync($this);
    }

    public function terminateServer($dispatch = true)
    {
        if ($dispatch) {
            OrderTerminateServer::dispatch($this);

            return;
        }

        OrderTerminateServer::dispatchSync($this);
    }

    public function emailOrderConfirmation(): void
    {
        $this->sendOrderEmail('order.confirmation');
    }

    public function emailOrderActivation(): void
    {
        $this->sendOrderEmail('order.activated');
    }

    public function emailOrderSuspension(): void
    {
        $this->sendOrderEmail('order.suspended');
    }

    public function emailOrderUnsuspension(): void
    {
        $this->sendOrderEmail('order.unsuspended');
    }

    public function emailOrderTermination(): void
    {
        $this->sendOrderEmail('order.terminated');
    }

    public function emailAutoBalanceRenewal(): void
    {
        $this->sendOrderEmail('order.renewed.balance');
    }

    public function emailNotEnoughBalanceForAutoRenewal(): void
    {
        $this->sendOrderEmail('order.renewal.insufficient_balance');
    }

    /**
     * @return array<string, mixed>
     */
    public function orderEmailVariables(): array
    {
        return [
            'package_name' => $this->package->name,
            'order_id' => $this->id,
            'cycle' => price($this->price).' / '.$this->cycle(),
            'status' => ucfirst($this->status),
            'due_date' => $this->due_date ? $this->due_date->format(settings('date_format', 'd M Y H:i')) : 'Never',
        ];
    }

    /**
     * @return array{columns: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public function orderEmailTable(): array
    {
        $variables = $this->orderEmailVariables();

        return [
            'columns' => [
                'Package',
                'Cycle',
                'Status',
                'Due Date',
            ],
            'rows' => [
                [
                    $variables['package_name'],
                    $variables['cycle'],
                    $variables['status'],
                    $variables['due_date'],
                ],
            ],
        ];
    }

    public function sendOrderEmail(string $identifier, array $variables = []): void
    {
        $this->user->email([
            'identifier' => $identifier,
            'mailable_type' => self::class,
            'mailable_id' => $this->id,
            'variables' => array_merge($this->orderEmailVariables(), $variables),
            'table' => $this->orderEmailTable(),
            'button' => [
                'url' => route('orders.view', ['order' => $this->id]),
            ],
        ]);
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
     * This method logs the changes made to the order model.
     * It checks if the user is dirty (i.e., has unsaved changes)
     * and if so, it logs the changes made to the specified fields.
     *
     * @param  Order  $order
     */
    public static function logOrderUpdates($order): void
    {
        $fieldsToLog = [
            'user_id',
            'package_id',
            'external_id',
            'status',
            'due_date',
            'last_renewed_at',
            'period_in_days',
        ];

        foreach ($fieldsToLog as $field) {
            if ($order->isDirty($field)) {
                $causer = auth()->user() ?? $order->user;
                $oldValue = $order->getOriginal($field);
                $newValue = $order->$field;

                // Log the change
                $order->logActivity([
                    'user_id' => $causer->id,
                    'event' => "order.updated.{$field}",
                    'description' => "Order {$field} updated by {$causer->username}",
                    'field' => $field,
                    'model_type' => Order::class,
                    'model_id' => $order->id,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ]);
            }
        }
    }

    // add search scope
    public function scopeSearch($query, $search)
    {
        return $query->where('external_id', 'like', '%'.$search.'%')
            ->orWhere('status', 'like', '%'.$search.'%')
            ->orWhere('due_date', 'like', '%'.$search.'%')
            ->orWhere('last_renewed_at', 'like', '%'.$search.'%');
    }
}
