<?php

namespace App\Models;

use App\Actions\PaymentActions;
use App\Events;
use App\Events\Payments\PaymentCompleted;
use App\Facades\Tax;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'token',
        'invoice_id',
        'user_id',
        'gateway_config_id',
        'subscription_id',
        'payable_type',
        'payable_id',
        'description',
        'status',
        'currency',
        'subtotal',
        'discount',
        'tax',
        'total',
        'earnings',
        'transaction_id',
        'success_url',
        'cancel_url',
        'handler',
        'data',
        'gateway_data',
        'paid_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:8',
        'discount' => 'decimal:8',
        'tax' => 'decimal:8',
        'total' => 'decimal:8',
        'earnings' => 'decimal:8',
        'data' => 'array',
        'gateway_data' => 'array',
        'paid_at' => 'datetime',
    ];

    protected $dispatchesEvents = [
        'created' => Events\Payments\PaymentCreated::class,
        'deleted' => Events\Payments\PaymentDeleted::class,
        'updated' => Events\Payments\PaymentUpdated::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            // if token is empty, generate a random token
            if (empty($payment->token)) {
                $payment->token = Str::random(32);
            }

            // if currency is empty, set it to the active currency
            if (empty($payment->currency)) {
                $payment->currency = settings('currency', 'USD');
            }

            // Normalize monetary fields so notifications/handlers can safely format amounts.
            if (is_null($payment->subtotal)) {
                $payment->subtotal = 0;
            }
            if (is_null($payment->discount)) {
                $payment->discount = 0;
            }
            if (is_null($payment->tax)) {
                $payment->tax = 0;
            }
            if (is_null($payment->total)) {
                $payment->total = (float) $payment->subtotal + (float) $payment->tax - (float) $payment->discount;
            }
            if (is_null($payment->earnings)) {
                $payment->earnings = $payment->total;
            }

            // set paid_at to now, will later be updated when payment is completed
            if (empty($payment->paid_at)) {
                $payment->paid_at = now();
            }
        });

        static::created(function ($payment) {
            // if invoice_id is empty, generate an invoice id
            if (empty($payment->invoice_id)) {
                $payment->invoice_id = self::generateInvoiceId($payment->id);
                $payment->save();
            }
        });
    }

    public static function actions()
    {
        return new PaymentActions;
    }

    // get total revenue from paid payments in the last X days, excluding gateway-balance payments
    public static function revenueLastDays($days = 30)
    {
        $balanceGateways = GatewayConfig::where('extension_identifier', 'gateway-balance')->pluck('id')->toArray();

        return self::where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subDays($days))
            ->whereNotIn('gateway_config_id', $balanceGateways)
            ->sum('total');
    }

    public static function paymentsLastDays($days = 30)
    {
        return self::where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subDays($days))
            ->count();
    }

    public function payable()
    {
        return $this->morphTo();
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

    public function completeManually($gatewayId, $transactionId)
    {
        $this->update([
            'gateway_config_id' => $gatewayId,
            'earnings' => $this->total(),
        ]);

        $this->completed($transactionId);
    }

    // add search scope
    public function scopeSearch($query, $search)
    {
        return $query->where('token', 'like', '%'.$search.'%')
            ->orWhere('description', 'like', '%'.$search.'%')
            ->orWhere('status', 'like', '%'.$search.'%')
            ->orWhere('total', 'like', '%'.$search.'%')
            ->orWhere('transaction_id', 'like', '%'.$search.'%')
            ->orWhere('currency', 'like', '%'.$search.'%');
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

    public function webhookUrl(array $params = [])
    {
        $routeParams = array_merge($params, [
            'webhook_id' => $this->gatewayConfig->webhook_id,
            'payment_token' => $this->token,
        ]);

        return route('payments.gateway.webhook', $routeParams);
    }

    public function callbackUrl(array $params = [])
    {
        $routeParams = array_merge($params, [
            'webhook_id' => $this->gatewayConfig->webhook_id,
            'payment_token' => $this->token,
        ]);

        return route('payments.gateway.callback', $routeParams);
    }

    public function logPaymentWebhook($message = 'Payment Webhook Received', $ipAddress = null, $headers = null, $payload = null)
    {
        $headers = $headers ?? request()->headers->all();

        $payload = $payload ?? request()->all();

        // remove any sensitive headers
        if (isset($headers['authorization'])) {
            $headers['authorization'] = ['REDACTED'];
        }

        // remove csrf token from headers
        if (isset($headers['x-csrf-token'])) {
            $headers['x-csrf-token'] = ['REDACTED'];
        }

        // remove cookies from headers
        if (isset($headers['cookie'])) {
            $headers['cookie'] = ['REDACTED'];
        }

        // remove cart from payload
        if (isset($payload['cart'])) {
            $payload['cart'] = 'REDACTED';
        }

        return PaymentWebhook::create([
            'payment_id' => $this->id,
            'ip_address' => $ipAddress ?? request()->ip(),
            'message' => $message,
            'headers' => $headers,
            'payload' => $payload,
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gatewayConfig()
    {
        return $this->belongsTo(GatewayConfig::class, 'gateway_config_id');
    }

    public function webhooks()
    {
        return $this->hasMany(PaymentWebhook::class);
    }

    public function refunds()
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function total()
    {
        return (float) ($this->total ?? 0);
    }

    public function isPaid()
    {
        return $this->status === 'paid';
    }

    public function isNotPaid()
    {
        return ! $this->isPaid();
    }

    public function completed($transactionId = null, array $paymentData = []): void
    {
        if ($this->isPaid()) {
            return;
        }

        $this->update([
            'status' => 'paid',
            'transaction_id' => $transactionId,
            'gateway_data' => $paymentData,
            'paid_at' => now(),
        ]);

        // Persist checkout tax metadata once payment is completed.
        $this->persistTaxDetailsFromPaymentData();

        $this->callHandler('onPaymentCompleted');

        PaymentCompleted::dispatch($this);

        $this->emailPaymentSuccess();
    }

    public function persistTaxDetailsFromPaymentData(): void
    {
        $taxDetails = $this->data('tax_details', []);

        if (! is_array($taxDetails) || empty($taxDetails)) {
            return;
        }

        if ($this->taxDetails) {
            return;
        }

        $hasAnyTaxDetail = ! empty($taxDetails['company_name'])
            || ! empty($taxDetails['tax_id'])
            || ! empty($taxDetails['country'])
            || ! empty($taxDetails['region'])
            || ! empty($taxDetails['zip_code']);

        if (! $hasAnyTaxDetail) {
            return;
        }

        $taxBreakdown = Tax::calculateSalesTax(
            $this->subtotal,
            $taxDetails['country'] ?? 'US',
            $taxDetails['region'] ?? null,
            $taxDetails['tax_id'] ?? null,
            $this->gateway_config_id
        );

        PaymentTaxDetail::updateOrCreate(
            ['payment_id' => $this->id],
            [
                'company_name' => $taxDetails['company_name'] ?? null,
                'tax_id' => $taxDetails['tax_id'] ?? null,
                'country' => $taxDetails['country'] ?? null,
                'region' => $taxDetails['region'] ?? null,
                'zip_code' => $taxDetails['zip_code'] ?? null,
                'tax_name' => $taxBreakdown['tax_name'] ?? 'Sales Tax',
                'tax_rate' => $taxBreakdown['tax_rate'] ?? 0,
            ]
        );
    }

    public function emailPaymentSuccess()
    {
        if (! $this->user) {
            return;
        }

        $this->user->email([
            'identifier' => 'payment.paid',
            'mailable_type' => self::class,
            'mailable_id' => $this->id,
            'variables' => [
                'description' => Str::limit($this->description, 50),
                'amount' => priceIn($this->total(), $this->currency),
                'transaction_id' => Str::limit($this->transaction_id, 32),
                'date' => now()->format(settings('date_format', 'd M Y H:i')),
            ],
            'table' => [
                'columns' => [
                    'Description',
                    'Amount',
                    'Transaction ID',
                    'Date',
                ],
                'rows' => [
                    [
                        Str::limit($this->description, 50),
                        priceIn($this->total(), $this->currency),
                        Str::limit($this->transaction_id, 32),
                        now()->format(settings('date_format', 'd M Y H:i')),
                    ],
                ],
            ],
            'button' => [
                'url' => route('payments.view', $this),
            ],
        ]);
    }

    public function callHandler($method)
    {
        if ($this->handler && method_exists($this->handler, $method)) {
            $handler = new $this->handler;
            $handler->$method($this);
        }
    }

    public function payWith($gatewayConfigId)
    {
        if ($this->isPaid()) {
            throw new \Exception('This payment has already been paid.');
        }

        // If payment price is zero, mark it as paid and redirect like other gateways.
        // Returning nothing here produced an empty HTTP response (white screen) on `/payments/pay/...`.
        if ((float) $this->total() === 0.0) {
            $this->completed();

            return redirect()->route('payments.completed');
        }

        $gatewayConfig = GatewayConfig::find($gatewayConfigId);

        if (! $gatewayConfig) {
            throw new \Exception("Could not locate '{$gatewayConfigId}' by id or alias.");
        }

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

        return $gatewayConfig->gateway->pay($this, $gatewayConfig);
    }

    public function convertTotalsToCurrency(string $currency)
    {
        return [
            'subtotal' => price($this->subtotal, to: $currency, in: $this->currency, absolute: true),
            'tax' => price($this->tax, to: $currency, in: $this->currency, absolute: true),
            'total' => price($this->total, to: $currency, in: $this->currency, absolute: true),
            'earnings' => price($this->earnings, to: $currency, in: $this->currency, absolute: true),
            'currency' => $currency,
        ];
    }

    public function taxDetails()
    {
        return $this->hasOne(PaymentTaxDetail::class);
    }

    public function data(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function addData(string $key, $value)
    {
        $this->data[$key] = $value;
        $this->save();
    }

    protected static function generateInvoiceId($id)
    {
        $invoiceFormat = settings('invoice_format', 'INV-{year}-{id}');
        $invoiceIdPadding = settings('invoice_id_padding', 4);

        // pad the id with zeros
        $id = str_pad($id, $invoiceIdPadding, '0', STR_PAD_LEFT);

        // generate the invoice id, by replacing {id}, {year} and {month} and {day} with the current date
        return str_replace(
            ['{id}', '{year}', '{month}', '{day}'],
            [$id, now()->format('Y'), now()->format('m'), now()->format('d')],
            $invoiceFormat
        );
    }

    public static function createWithTax(array $attributes, $subtotal, $country, $region = null, $taxId = null, $gatewayConfigId = null)
    {
        $attributes['subtotal'] = $subtotal;

        $payment = Payment::create($attributes);

        $payment->calculateSalesTax($country, $region, $taxId, $gatewayConfigId);

        return $payment;
    }

    public function calculateSalesTax($countryCode, $regionCode = null, $taxId = null, $gatewayConfigId = null)
    {
        $taxBreakdown = Tax::calculateSalesTax(
            $this->subtotal,
            $countryCode,
            $regionCode,
            $taxId,
            $gatewayConfigId ?? $this->gateway_config_id
        );

        $this->update([
            'tax' => $taxBreakdown['tax_amount'],
            'total' => $taxBreakdown['amount_after_tax'],
            'earnings' => $taxBreakdown['amount_before_tax'],
        ]);

        return $taxBreakdown;
    }
}
