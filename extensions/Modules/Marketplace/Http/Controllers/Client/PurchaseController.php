<?php

namespace Extensions\Modules\Marketplace\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Extensions\Modules\Marketplace\Gateways\PayPalIpn\PayPalIpnGateway;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Models\MarketplaceSale;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function start(Request $request, MarketplaceResource $resource)
    {
        abort_unless($resource->isVisibleTo(auth()->user()), 404);

        try {
            $sale = MarketplaceSale::actions()->startCheckout([
                'user_id' => auth()->id(),
                'resource_id' => $resource->id,
                'gateway_config_id' => $request->input('gateway_config_id'),
            ]);
        } catch (ValidationException $exception) {
            return redirect($resource->clientUrl())
                ->with('error', collect($exception->errors())->flatten()->first() ?: 'Checkout could not be started.');
        }

        $sale->load(['resource.category', 'gatewayConfig']);

        if (! $sale->gatewayConfig) {
            return redirect($resource->clientUrl())
                ->with('error', 'The creator has not configured a payment method yet.');
        }

        try {
            return $sale->gatewayConfig->driver()->checkout($sale, $sale->gatewayConfig);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect($resource->clientUrl())
                ->with('error', 'Checkout could not be started. Please try again later.');
        }
    }

    public function paypal(MarketplaceSale $sale)
    {
        abort_unless((int) $sale->buyer_id === (int) auth()->id(), 403);

        $sale->load(['resource', 'gatewayConfig']);

        abort_unless($sale->gatewayConfig?->driver === 'paypal_ipn', 404);

        $gateway = $sale->gatewayConfig->driver();

        abort_unless($gateway instanceof PayPalIpnGateway, 404);

        return client_view('marketplace::marketplace.paypal-redirect', [
            'sale' => $sale,
            'action' => $gateway->checkoutUrl($sale->gatewayConfig),
            'fields' => $gateway->checkoutFields($sale, $sale->gatewayConfig),
        ]);
    }

    public function returned(Request $request, MarketplaceSale $sale)
    {
        abort_unless((int) $sale->buyer_id === (int) auth()->id(), 403);

        $sale->load(['resource.category', 'gatewayConfig']);

        if (! $sale->gatewayConfig) {
            return redirect($sale->resource->clientUrl());
        }

        return $sale->gatewayConfig->driver()->handleReturn($request, $sale, $sale->gatewayConfig);
    }

    public function cancel(MarketplaceSale $sale)
    {
        abort_unless((int) $sale->buyer_id === (int) auth()->id(), 403);

        MarketplaceSale::actions()->fail($sale);

        return redirect($sale->resource->clientUrl())
            ->with('error', 'Checkout was cancelled.');
    }
}
