<?php

namespace Extensions\Gateways\PaddleCheckout;

use App\Extensions\Foundation\GatewayExtension;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\GatewayConfig;
use App\Models\Payment;
use Exception;

class Gateway extends GatewayExtension
{
    /**
     * Define the extension identifier. This identifier should be unique.
     *
     * @var string
     */
    protected string $id = 'gateway-paddle-checkout';

    /**
     * Define the extension display name
     *
     * @var string
     */
    protected string $name = 'Paddle Checkout';

    /**
     * Define the extension description.
     *
     * @var string
     */
    protected string $description = 'Accept payments online using Paddle\'s hosted checkout (Paddle Billing).';

    /**
     * Define the extension type. For example, if the extension is a module, the extension type should be "Module".
     *
     * @var string
     */
    protected string $type = 'Gateway';

    /**
     * Define the gateway type. Can be "subscription" or "payment".
     *
     * @var string
     */
    protected string $gatewayType = 'payment';

    /**
     * Define the supported currencies. Empty array means all currencies are supported.
     *
     * @var array
     */
    protected array $currencies = [];

    /**
     * Define the extension version.
     *
     * @var string
     */
    protected string $version = '1.0.0';

    /**
     * Define the WemX versions that the extension is compatible with.
     *
     * @var array
     */
    protected array $wemxVersions = [
        '*',
    ];

    /**
     * Define the authors of the extension.
     *
     * @var array
     */
    protected array $authors = [
        [
            'name' => 'OpsLab',
            'email' => 'claude-ops@opslabsystems.cloud',
        ]
    ];

    /**
     * Define the extension display description to customers.
     *
     * @var string
     */
    public string $gatewayDescription = 'Pay securely by card, PayPal, or other local payment methods via Paddle.';

    /**
     * Define the default configuration values required to setup this gateway.
     *
     * @return array
     */
    public static function setConfig(): array
    {
        return [
            'api_key' => [
                'label' => 'Paddle API Key',
                'description' => 'Paddle Dashboard → Developer Tools → Authentication. Use a sandbox API key while "Sandbox Mode" below is enabled.',
                'type' => 'text',
                'rules' => ['required', 'string'],
            ],
            'client_side_token' => [
                'label' => 'Client-side Token',
                'description' => 'Paddle Dashboard → Developer Tools → Authentication → Client-side tokens (a separate, publicly-safe token — not the API Key above). Required to actually render Paddle\'s checkout overlay in the customer\'s browser.',
                'type' => 'text',
                'rules' => ['required', 'string'],
            ],
            'webhook_secret' => [
                'label' => 'Notification Secret Key',
                'description' => 'Create a notification destination in Paddle Dashboard → Developer Tools → Notifications pointing at this gateway\'s webhook URL (shown below once saved), subscribed to the "transaction.completed" event. Paste that destination\'s secret key here.',
                'type' => 'text',
                'rules' => ['required', 'string'],
            ],
            'sandbox' => [
                'label' => 'Sandbox Mode',
                'description' => 'Enable while testing with a Paddle sandbox account. Disable to process real payments.',
                'type' => 'select',
                'options' => [
                    '0' => 'Disabled (Live)',
                    '1' => 'Enabled (Sandbox)',
                ],
                'rules' => ['required'],
                'default_value' => '0',
            ],
        ];
    }

    /**
     * Handle the payment process.
     * Creates a Paddle transaction, then redirects the customer to Paddle's hosted checkout.
     *
     * @param \App\Models\Payment $payment
     * @param \App\Models\GatewayConfig $gatewayConfig
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Exception
     */
    public function pay(Payment $payment, GatewayConfig $gatewayConfig)
    {
        $this->ensureDefaultCheckoutUrlConfigured($gatewayConfig);

        $response = Http::withToken($gatewayConfig->config('api_key'))
            ->acceptJson()
            ->post($this->apiUrl($gatewayConfig, '/transactions'), [
                'currency_code' => $payment->currency,
                'collection_mode' => 'automatic',
                'custom_data' => [
                    'payment_token' => $payment->token,
                ],
                'items' => [[
                    'quantity' => 1,
                    'price' => [
                        'description' => $payment->description,
                        'name' => $payment->description,
                        'unit_price' => [
                            'amount' => $this->toMinorUnits($payment->total()),
                            'currency_code' => $payment->currency,
                        ],
                        // Non-catalog price: Paddle requires either product_id (an existing
                        // catalog product) or a nested product object to create one inline.
                        // We have no Paddle catalog product to reference, so create inline.
                        'product' => [
                            'name' => $payment->description,
                            'tax_category' => 'standard',
                        ],
                    ],
                ]],
            ]);

        if ($response->failed()) {
            throw new Exception('Paddle transaction creation failed: ' . $response->body());
        }

        $transaction = $response->json('data');
        $checkoutUrl = $transaction['checkout']['url'] ?? null;

        if (! $checkoutUrl) {
            throw new Exception('Paddle did not return a checkout URL. Configure a default payment link in your Paddle Dashboard → Checkout settings.');
        }

        // store the transaction id locally so the webhook/callback can find this payment
        $payment->update([
            'transaction_id' => $transaction['id'],
        ]);

        return redirect($checkoutUrl);
    }

    /**
     * Handle the browser redirect back from Paddle's hosted checkout.
     * Paddle's default payment link does not carry our payment token back on
     * return, so this re-fetches the transaction status directly as a
     * best-effort confirmation — the webhook() below is the authoritative
     * source of truth for marking a payment paid.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\GatewayConfig $gatewayConfig
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Exception
     */
    public function callback(Request $request, GatewayConfig $gatewayConfig)
    {
        if ($request->has('payment_token')) {
            $payment = Payment::where('token', $request->payment_token)->first();

            if (! $payment) {
                throw new Exception('Payment not found');
            }

            if ($payment->isPaid()) {
                return redirect($payment->successUrl());
            }

            if ($payment->transaction_id) {
                $response = Http::withToken($gatewayConfig->config('api_key'))
                    ->acceptJson()
                    ->get($this->apiUrl($gatewayConfig, '/transactions/' . $payment->transaction_id));

                if ($response->successful()) {
                    $transaction = $response->json('data');

                    // defensive check: make sure this transaction really belongs to this payment
                    if (($transaction['custom_data']['payment_token'] ?? null) === $payment->token
                        && in_array($transaction['status'] ?? null, ['paid', 'completed'], true)) {
                        $payment->completed($transaction['id'], $transaction);
                        $payment->logPaymentWebhook('Payment completed via callback');
                    }
                }
            }

            return redirect($payment->successUrl());
        }

        return redirect('/');
    }

    /**
     * Handle webhooks (notifications) from Paddle.
     * Paddle calls this endpoint whenever a subscribed event occurs — this
     * only acts on "transaction.completed" and acknowledges everything else
     * with a 200 so Paddle doesn't retry/flag unrelated event types.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\GatewayConfig $gatewayConfig
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function webhook(Request $request, GatewayConfig $gatewayConfig)
    {
        $this->verifyPaddleWebhook($request, $gatewayConfig->config('webhook_secret'));

        $eventType = $request->input('event_type');

        if ($eventType !== 'transaction.completed') {
            return response()->json(['message' => 'Event ignored'], 200);
        }

        $transactionId = $request->input('data.id');

        if (! $transactionId) {
            throw new Exception('Transaction ID not found');
        }

        $payment = Payment::where('transaction_id', $transactionId)->first();

        if (! $payment) {
            throw new Exception('Payment not found');
        }

        if ($payment->isPaid()) {
            return response()->json(['message' => 'Payment already completed'], 200);
        }

        $payment->completed($transactionId, $request->input('data'));
        $payment->logPaymentWebhook('Payment completed via webhook');

        return response()->json(['message' => 'Payment completed'], 200);
    }

    /**
     * Issue a refund via Paddle's Adjustments API.
     * Full refund when $amount is omitted; partial otherwise (Paddle requires
     * partial refunds to reference the original transaction's line item).
     *
     * @param \App\Models\Payment $payment
     * @param float|null $amount
     * @return array
     *
     * @throws \Exception
     */
    public function refund(Payment $payment, $amount = null)
    {
        $gatewayConfig = $payment->gatewayConfig;

        if (! $payment->transaction_id) {
            throw new Exception('This payment has no Paddle transaction to refund.');
        }

        $body = [
            'action' => 'refund',
            'transaction_id' => $payment->transaction_id,
            'reason' => 'Refund issued via WemX',
        ];

        if ($amount === null) {
            $body['type'] = 'full';
        } else {
            $itemsResponse = Http::withToken($gatewayConfig->config('api_key'))
                ->acceptJson()
                ->get($this->apiUrl($gatewayConfig, '/transactions/' . $payment->transaction_id));

            if ($itemsResponse->failed()) {
                throw new Exception('Could not look up Paddle transaction for a partial refund: ' . $itemsResponse->body());
            }

            $items = $itemsResponse->json('data.items', []);

            if (empty($items)) {
                throw new Exception('Could not determine the Paddle transaction line item for a partial refund.');
            }

            $body['type'] = 'partial';
            $body['items'] = [[
                'item_id' => $items[0]['id'],
                'type' => 'partial',
                'amount' => $this->toMinorUnits($amount),
            ]];
        }

        $response = Http::withToken($gatewayConfig->config('api_key'))
            ->acceptJson()
            ->post($this->apiUrl($gatewayConfig, '/adjustments'), $body);

        if ($response->failed()) {
            throw new Exception('Paddle refund failed: ' . $response->body());
        }

        return $response->json('data');
    }

    /**
     * Paddle refuses to create a transaction / hosted checkout session until
     * the account has a "default payment link" set (Dashboard → Checkout →
     * Checkout settings), otherwise it fails with
     * transaction_default_checkout_url_not_set. Rather than make the merchant
     * configure that by hand, set it ourselves via the API the first time it's
     * missing. We only ever fill it in when empty — if an admin has already
     * set a custom one via the Dashboard, we leave it alone.
     */
    private function ensureDefaultCheckoutUrlConfigured(GatewayConfig $gatewayConfig): void
    {
        $response = Http::withToken($gatewayConfig->config('api_key'))
            ->acceptJson()
            ->get($this->apiUrl($gatewayConfig, '/settings/checkout-url'));

        if ($response->failed()) {
            return;
        }

        if (! empty($response->json('data.default_url'))) {
            return;
        }

        Http::withToken($gatewayConfig->config('api_key'))
            ->acceptJson()
            ->patch($this->apiUrl($gatewayConfig, '/settings/checkout-url'), [
                'default_url' => route('payments.paddle.checkout'),
            ]);
    }

    /**
     * Build a full API URL for the configured environment (sandbox or live).
     */
    private function apiUrl(GatewayConfig $gatewayConfig, string $path): string
    {
        $base = ((string) $gatewayConfig->config('sandbox', '0') === '1')
            ? 'https://sandbox-api.paddle.com'
            : 'https://api.paddle.com';

        return $base . $path;
    }

    /**
     * Paddle amounts are integer strings in the currency's smallest unit (e.g. cents).
     */
    private function toMinorUnits(float $amount): string
    {
        return (string) (int) round($amount * 100);
    }

    /**
     * Verify the "Paddle-Signature" header against the raw request body.
     * Format: ts=<unix_timestamp>;h1=<hex hmac-sha256 of "ts:raw_body">
     *
     * @throws \Exception
     */
    private function verifyPaddleWebhook(Request $request, ?string $webhookSecret): void
    {
        if (! $webhookSecret) {
            throw new Exception('Paddle webhook secret is not configured.');
        }

        $sigHeader = $request->header('Paddle-Signature');

        if (! $sigHeader) {
            throw new Exception('Paddle-Signature header is missing.');
        }

        $timestamp = null;
        $signature = null;

        foreach (explode(';', $sigHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 'ts') {
                $timestamp = $value;
            } elseif ($key === 'h1') {
                $signature = $value;
            }
        }

        if (! $timestamp || ! $signature) {
            throw new Exception('Invalid Paddle-Signature header format.');
        }

        $rawBody = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $timestamp . ':' . $rawBody, $webhookSecret);

        if (! hash_equals($expectedSignature, $signature)) {
            throw new Exception('Invalid Paddle webhook signature.');
        }
    }
}
