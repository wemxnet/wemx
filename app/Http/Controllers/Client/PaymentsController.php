<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Invoices\InvoiceTheme;
use App\Models\GatewayConfig;
use App\Models\GatewayWebhookLog;
use App\Models\Payment;
use App\Models\Subscription;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    public function downloadInvoicePdf(Payment $payment)
    {
        abort_if(! settings('allow_client_pdf_invoices', false), 403);

        // If payment belongs to a user, only that user can download it.
        if ($payment->user_id && (! auth()->check() || auth()->id() !== $payment->user_id)) {
            abort(403);
        }

        $theme = InvoiceTheme::default();

        $pdf = Pdf::loadView($theme->view(), $theme->viewData($payment));

        return $pdf->download('invoice-'.($payment->invoice_id ?: $payment->id).'.pdf');
    }

    public function pay($gatewayConfigId, Payment $payment)
    {
        $gatewayConfig = GatewayConfig::findOrFail($gatewayConfigId);

        // ensure gateway is active
        if (! $gatewayConfig->is_active) {
            return redirect()->back();
        }

        // ensure the gateway is a payment gateway
        if ($gatewayConfig->type != 'payment') {
            return redirect()->back();
        }

        // Prevent using sandbox gateways for users that don't have the permission
        if ($gatewayConfig->is_staff_only) {
            if (! auth()->check() or ! auth()->user()->hasPermission('use-staff-gateways')) {
                return redirect()->back();
            }
        }

        // Prevent using the balance gateway for non-balance topup payments
        if ($payment->handler == 'App\Handlers\BalanceTopupHandler' and $gatewayConfig->extension_identifier == 'gateway-balance') {
            return redirect()->back();
        }

        return $payment->payWith($gatewayConfig->id);
    }

    public function subscribe($gatewayConfigId, Subscription $subscription)
    {
        $gatewayConfig = GatewayConfig::findOrFail($gatewayConfigId);

        // ensure gateway is active
        if (! $gatewayConfig->is_active) {
            return redirect()->back();
        }

        // ensure the gateway is a subscription gateway
        if ($gatewayConfig->type != 'subscription') {
            return redirect()->back();
        }

        // Prevent using sandbox gateways for users that don't have the permission
        if ($gatewayConfig->is_staff_only) {
            if (! auth()->check() or ! auth()->user()->hasPermission('use-staff-gateways')) {
                return redirect()->back();
            }
        }

        return $subscription->subscribeWith($gatewayConfig);
    }

    public function gatewayWebhook($webhookId, Request $request)
    {
        $gatewayConfig = GatewayConfig::where('webhook_id', $webhookId)->firstOrFail();

        GatewayWebhookLog::create([
            'gateway_config_id' => $gatewayConfig->id,
            'ip_address' => $request->ip(),
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
            'is_successful' => true,
            'message' => 'Webhook received',
        ]);

        try {
            if (! $gatewayConfig->gateway->hasWebhook()) {
                return response()->json([
                    'success' => false,
                    'code' => 404,
                    'message' => 'This gateway does not support webhooks.',
                ], 404);
            }

            return $gatewayConfig->gateway->handleWebhook($request, $gatewayConfig);
        } catch (\Exception $error) {

            GatewayWebhookLog::create([
                'gateway_config_id' => $gatewayConfig->id,
                'ip_address' => $request->ip(),
                'headers' => $request->headers->all(),
                'payload' => $request->all(),
                'is_successful' => false,
                'message' => 'Webhook error ['.get_class($error).']: '.$error->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'code' => 500,
                'message' => $error->getMessage(),
            ], 500);
        }
    }

    public function gatewayCallback($webhookId, Request $request)
    {
        $gatewayConfig = GatewayConfig::where('webhook_id', $webhookId)->firstOrFail();

        try {
            if (! $gatewayConfig->gateway->hasCallback()) {
                return response()->json([
                    'success' => false,
                    'code' => 404,
                    'message' => 'This gateway does not support callbacks.',
                ], 404);
            }

            return $gatewayConfig->gateway->handleCallback($request, $gatewayConfig);
        } catch (\Exception $error) {
            return response()->json([
                'success' => false,
                'code' => 500,
                'message' => $error->getMessage(),
            ], 500);
        }
    }
}
