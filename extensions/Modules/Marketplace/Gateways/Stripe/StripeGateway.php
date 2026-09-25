<?php

namespace Extensions\Modules\Marketplace\Gateways\Stripe;

use Extensions\Modules\Marketplace\Enums\SaleStatus;
use Extensions\Modules\Marketplace\Gateways\CreatorGatewayFoundation;
use Extensions\Modules\Marketplace\Models\MarketplaceCreatorGatewayConfig;
use Extensions\Modules\Marketplace\Models\MarketplaceSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class StripeGateway extends CreatorGatewayFoundation
{
    public function id(): string
    {
        return 'stripe';
    }

    public function name(): string
    {
        return 'Stripe';
    }

    public function description(): string
    {
        return 'Accept card payments through your own Stripe account.';
    }

    public function credentialFields(): array
    {
        return [
            'secret_key' => [
                'label' => 'Secret key',
                'description' => 'Starts with sk_test_ or sk_live_.',
                'type' => 'password',
                'rules' => ['required', 'string', 'starts_with:sk_'],
            ],
            'publishable_key' => [
                'label' => 'Publishable key',
                'description' => 'Starts with pk_test_ or pk_live_.',
                'type' => 'text',
                'rules' => ['required', 'string', 'starts_with:pk_'],
            ],
            'webhook_secret' => [
                'label' => 'Webhook secret',
                'description' => 'Optional. Used to verify Stripe webhooks. Starts with whsec_.',
                'type' => 'password',
                'rules' => ['nullable', 'string', 'starts_with:whsec_'],
            ],
        ];
    }

    public function checkout(MarketplaceSale $sale, MarketplaceCreatorGatewayConfig $config): Response
    {
        $response = Http::withToken((string) $config->credential('secret_key'))
            ->asForm()
            ->timeout(20)
            ->connectTimeout(5)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($sale->currency),
                        'unit_amount' => (int) round(((float) $sale->amount) * 100),
                        'product_data' => [
                            'name' => $sale->resource->name,
                            'description' => $sale->resource->short_description,
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => url('/marketplace/checkout/'.$sale->uuid.'/return').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => url('/marketplace/checkout/'.$sale->uuid.'/cancel'),
                'client_reference_id' => $sale->uuid,
                'metadata' => [
                    'sale_uuid' => $sale->uuid,
                    'resource_id' => (string) $sale->resource_id,
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Stripe could not start checkout.');
        }

        $session = $response->json();

        $sale->update([
            'gateway_reference' => $session['id'] ?? null,
        ]);

        return redirect($session['url']);
    }

    public function handleReturn(Request $request, MarketplaceSale $sale, MarketplaceCreatorGatewayConfig $config): Response
    {
        if ($sale->isCompleted()) {
            return $this->successRedirect($sale);
        }

        $sessionId = $request->string('session_id')->toString() ?: $sale->gateway_reference;

        if (! $sessionId) {
            return $this->successRedirect($sale);
        }

        $response = Http::withToken((string) $config->credential('secret_key'))
            ->timeout(15)
            ->connectTimeout(5)
            ->get('https://api.stripe.com/v1/checkout/sessions/'.$sessionId);

        if ($response->successful() && ($response->json('payment_status') === 'paid')) {
            MarketplaceSale::actions()->complete($sale, [
                'gateway_reference' => $sessionId,
                'gateway_payload' => $response->json(),
            ]);
        }

        return $this->successRedirect($sale->fresh());
    }

    public function handleWebhook(Request $request, MarketplaceCreatorGatewayConfig $config): Response
    {
        $secret = $config->credential('webhook_secret');

        if (filled($secret)) {
            $this->verifySignature($request, (string) $secret);
        }

        $type = $request->input('type');
        $object = $request->input('data.object', []);

        if ($type !== 'checkout.session.completed' && data_get($object, 'payment_status') !== 'paid') {
            return response()->json(['received' => true]);
        }

        $sale = MarketplaceSale::query()
            ->where('uuid', data_get($object, 'metadata.sale_uuid'))
            ->orWhere('gateway_reference', data_get($object, 'id'))
            ->first();

        if (! $sale || $sale->status === SaleStatus::Completed) {
            return response()->json(['received' => true]);
        }

        MarketplaceSale::actions()->complete($sale, [
            'gateway_reference' => data_get($object, 'id'),
            'gateway_payload' => $object,
        ]);

        return response()->json(['received' => true]);
    }

    protected function verifySignature(Request $request, string $secret): void
    {
        $header = $request->header('Stripe-Signature');

        if (! $header) {
            abort(400, 'Missing Stripe signature.');
        }

        $timestamp = null;
        $signature = null;

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't') {
                $timestamp = $value;
            }

            if ($key === 'v1') {
                $signature = $value;
            }
        }

        if (! $timestamp || ! $signature) {
            abort(400, 'Invalid Stripe signature.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(400, 'Invalid Stripe signature.');
        }
    }

    protected function successRedirect(MarketplaceSale $sale): Response
    {
        return redirect($sale->resource->clientUrl())
            ->with('success', $sale->isCompleted()
                ? 'Payment received. Your license is ready to download.'
                : 'Payment is still processing. Refresh this page in a moment.');
    }
}
