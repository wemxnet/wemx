<?php

namespace Extensions\Gateways\MollieCheckout;

use App\Extensions\Foundation\GatewayExtension;
use App\Models\GatewayConfig;
use App\Models\Payment;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class Gateway extends GatewayExtension
{
    /**
     * Define the extension identifier. This identifier should be unique.
     * For example, if the extension name is "Example Module", the extension identifier should be "module-example".
     */
    protected string $id = 'gateway-mollie-checkout';

    /**
     * Define the extension display name
     */
    protected string $name = 'Mollie Checkout';

    /**
     * Define the extension description.
     */
    protected string $description = 'Accept payments online using Mollie Checkout.';

    /**
     * Define the extension type. For example, if the extension is a module, the extension type should be "Module".
     */
    protected string $type = 'Gateway';

    /**
     * Define the gateway type. Can be "subscription" or "payment".
     */
    protected string $gatewayType = 'payment';

    /**
     * Define the supported currencies.
     */
    protected array $currencies = [];

    public string $marketplace_id = '1';

    /**
     * Define the extension version.
     */
    protected string $version = '1.0.0';

    /**
     * Define the WemX versions that the extension is compatible with.
     * Use * to define that the extension is compatible with all versions.
     */
    protected array $wemxVersions = [
        '*',
    ];

    /**
     * Define the authors of the extension.
     */
    protected array $authors = [
        [
            'name' => 'WemX',
            'email' => 'team@wemx.net',
        ],
    ];

    /**
     * Define the extension display description to customers.
     */
    public string $gatewayDescription = 'Pay easily using Mollie\'s hosted checkout page.';

    /**
     * Define the default configuration values required to setup this gateway
     * i.e host, api key, or other values. Use Laravel validation rules for
     *
     * Laravel validation rules: https://laravel.com/docs/10.x/validation
     */
    public static function setConfig(): array
    {
        return [
            'api_key' => [
                'label' => 'Mollie API Key',
                'description' => 'Enter your Mollie API Key here.',
                'type' => 'text',
                'rules' => ['required', 'string'],
            ],
        ];
    }

    /**
     * Handle the payment process.
     * This method should create a payment, then redirect the user to the payment gateway for approval.
     *
     * If something goes wrong, you can throw an exception to return a 500 error.
     * Do not include any sensitive in the exception message.
     *
     * To retrieve config values, you can use $gatewayConfig->config('key', 'default_value').
     *
     * @return RedirectResponse
     *
     * @throws Exception
     */
    public function pay(Payment $payment, GatewayConfig $gatewayConfig)
    {
        // Prepare Mollie payment creation parameters
        $requestData = [
            'amount' => [
                'currency' => $payment->currency,
                // Mollie expects a string in the format "12.99", so format the float:
                'value' => number_format($payment->total(), 2, '.', ''),
            ],
            'description' => $payment->description,
            'cancelUrl' => $payment->cancelUrl(),
            // The URL the customer will be redirected to after the payment process
            'redirectUrl' => $payment->callbackUrl(),
            // Your webhook to receive asynchronous payment status updates
            // 'webhookUrl'  => $payment->webhookUrl(),
            // Some metadata to help you identify the payment in your system
            'metadata' => [
                'payment_id' => $payment->id,
            ],
        ];

        // Make the API call to Mollie
        $response = Http::withToken($gatewayConfig->config('api_key'))
            ->post('https://api.mollie.com/v2/payments', $requestData);

        if ($response->failed()) {
            throw new Exception('Failed to create the payment using Mollie API');
        }

        // Store Mollie's payment ID in your local Payment record
        $molliePayment = $response->json();
        $payment->update([
            // "id" is Mollie's identifier for this payment
            'transaction_id' => $molliePayment['id'],
        ]);

        // Redirect user to the Mollie checkout page
        if (isset($molliePayment['_links']['checkout']['href'])) {
            return redirect()->away($molliePayment['_links']['checkout']['href']);
        }

        // In a rare case Mollie didn't provide the checkout URL:
        throw new Exception('Mollie checkout URL not found in the response');
    }

    public function callback(Request $request, GatewayConfig $gatewayConfig)
    {
        // We retrieve our Payment record:
        $payment = Payment::where('token', $request->query('payment_token'))->first();

        if (! $payment) {
            throw new Exception('Payment record not found');
        }

        if ($payment->isPaid()) {
            return redirect($payment->successUrl());
        }

        // Use the transaction ID we stored earlier to fetch the latest
        // payment details from Mollie and confirm status, amount, etc.
        $transactionId = $payment->transaction_id;

        if (! $transactionId) {
            throw new Exception('Missing Mollie transaction ID on Payment record');
        }

        // Fetch payment status from Mollie
        $response = Http::withToken($gatewayConfig->config('api_key'))
            ->get("https://api.mollie.com/v2/payments/{$transactionId}");

        if ($response->failed()) {
            throw new Exception('Failed to retrieve the payment from Mollie');
        }

        $molliePayment = $response->json();

        // Check if Mollie says the payment is "paid"
        if (isset($molliePayment['status']) && $molliePayment['status'] === 'paid') {
            $payment->completed($molliePayment['id'], $molliePayment);
            $payment->logPaymentWebhook('Payment completed via callback');

            return redirect($payment->successUrl());
        } else {
            // You could optionally handle other statuses such as "canceled" or "expired"
            throw new Exception('Unexpected or incomplete payment status');
        }
    }

    /**
     * Handle (webhooks) from Mollie.
     * Mollie calls this endpoint whenever the payment status changes.
     *
     * If something goes wrong, you can throw an exception to return a 500 error.
     * Do not include any sensitive in the exception message.
     *
     * Return a 200 json response to acknowledge the webhook.
     *
     * @param  GatewayConfig  $gatwewayConfig
     * @return JsonResponse
     *
     * @throws Exception
     *
     * Example response:
     * return response()->json([
     *     'success' => true,
     *     'message' => 'Webhook received successfully',
     * ]);
     */
    public function webhook(Request $request, GatewayConfig $gatewayConfig)
    {
        return response()->json([
            'message' => 'Mollie does not support webhooks for this gateway integration. Please use the callback URL instead.',
        ], 200);
    }
}
