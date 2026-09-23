<?php

namespace Extensions\Modules\Marketplace\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Extensions\Modules\Marketplace\Gateways\CreatorGatewayRegistry;
use Extensions\Modules\Marketplace\Models\MarketplaceCreatorGatewayConfig;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GatewayWebhookController extends Controller
{
    public function handle(Request $request, string $driver, MarketplaceCreatorGatewayConfig $config): Response
    {
        abort_unless($config->driver === $driver && CreatorGatewayRegistry::exists($driver), 404);

        return $config->driver()->handleWebhook($request, $config);
    }
}
