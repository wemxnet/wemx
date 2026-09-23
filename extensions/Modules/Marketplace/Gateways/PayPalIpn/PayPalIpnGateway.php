<?php

namespace Extensions\Modules\Marketplace\Gateways\PayPalIpn;

use Extensions\Modules\Marketplace\Enums\SaleStatus;
use Extensions\Modules\Marketplace\Gateways\CreatorGatewayFoundation;
use Extensions\Modules\Marketplace\Models\MarketplaceCreatorGatewayConfig;
use Extensions\Modules\Marketplace\Models\MarketplaceSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class PayPalIpnGateway extends CreatorGatewayFoundation
{
    public function id(): string
    {
        return 'paypal_ipn';
    }

    public function name(): string
    {
        return 'PayPal IPN';
    }

    public function description(): string
    {
        return 'Collect PayPal payments using your PayPal email and Instant Payment Notification.';
    }

    public function credentialFields(): array
    {
        return [
            'email' => [
                'label' => 'PayPal email',
                'description' => 'The email address that receives payments.',
                'type' => 'email',
                'rules' => ['required', 'email'],
            ],
            'mode' => [
                'label' => 'Mode',
                'description' => 'Use sandbox while testing, live for production.',
                'type' => 'select',
                'options' => ['sandbox' => 'Sandbox', 'live' => 'Live'],
                'rules' => ['required', 'in:sandbox,live'],
            ],
        ];
    }

    public function checkout(MarketplaceSale $sale, MarketplaceCreatorGatewayConfig $config): Response
    {
        return redirect(url('/marketplace/checkout/'.$sale->uuid.'/paypal'));
    }

    /**
     * @return array<string, string>
     */
    public function checkoutFields(MarketplaceSale $sale, MarketplaceCreatorGatewayConfig $config): array
    {
        return [
            'cmd' => '_xclick',
            'business' => (string) $config->credential('email'),
            'item_name' => $sale->resource->name,
            'item_number' => $sale->uuid,
            'amount' => number_format((float) $sale->amount, 2, '.', ''),
            'currency_code' => $sale->currency,
            'custom' => $sale->uuid,
            'notify_url' => url('/marketplace/webhooks/'.$this->id().'/'.$config->id),
            'return' => url('/marketplace/checkout/'.$sale->uuid.'/return'),
            'cancel_return' => url('/marketplace/checkout/'.$sale->uuid.'/cancel'),
            'rm' => '2',
            'no_note' => '1',
            'charset' => 'utf-8',
        ];
    }

    public function checkoutUrl(MarketplaceCreatorGatewayConfig $config): string
    {
        return $this->isSandbox($config)
            ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://www.paypal.com/cgi-bin/webscr';
    }

    public function handleWebhook(Request $request, MarketplaceCreatorGatewayConfig $config): Response
    {
        $payload = $request->post();

        if (! $this->verifyIpn($payload, $config)) {
            abort(400, 'Invalid PayPal IPN.');
        }

        $sale = MarketplaceSale::query()
            ->where('uuid', $request->input('custom', $request->input('item_number')))
            ->first();

        if (! $sale || $sale->status === SaleStatus::Completed) {
            return response('OK', 200);
        }

        if (strcasecmp((string) $request->input('receiver_email'), (string) $config->credential('email')) !== 0) {
            abort(400, 'PayPal receiver mismatch.');
        }

        if ((string) $request->input('mc_currency') !== $sale->currency) {
            abort(400, 'PayPal currency mismatch.');
        }

        if ((float) $request->input('mc_gross') + 0.009 < (float) $sale->amount) {
            abort(400, 'PayPal amount mismatch.');
        }

        if (! in_array($request->input('payment_status'), ['Completed', 'Processed'], true)) {
            return response('OK', 200);
        }

        MarketplaceSale::actions()->complete($sale, [
            'gateway_reference' => $request->input('txn_id'),
            'gateway_payload' => $payload,
        ]);

        return response('OK', 200);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyIpn(array $payload, MarketplaceCreatorGatewayConfig $config): bool
    {
        $verifyUrl = $this->isSandbox($config)
            ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://ipnpb.paypal.com/cgi-bin/webscr';

        $response = Http::asForm()
            ->timeout(20)
            ->connectTimeout(5)
            ->withBody(http_build_query(['cmd' => '_notify-validate'] + $payload), 'application/x-www-form-urlencoded')
            ->post($verifyUrl);

        return $response->successful() && str_contains($response->body(), 'VERIFIED');
    }

    protected function isSandbox(MarketplaceCreatorGatewayConfig $config): bool
    {
        return $config->credential('mode', 'live') === 'sandbox';
    }
}
