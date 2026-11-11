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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    /**
     * Paddle's "default payment link" destination. Paddle appends ?_ptxn={transaction_id}
     * to this URL once a transaction is created, and Paddle.js (embedded in the view)
     * reads that parameter to open the actual checkout overlay for the customer.
     */
    public function paddleCheckout(Request $request)
    {
        $transactionId = (string) $request->query('_ptxn', '');

        $payment = $transactionId !== ''
            ? Payment::where('transaction_id', $transactionId)->first()
            : null;

        abort_if(! $payment, 404);

        $gatewayConfig = $payment->gatewayConfig;

        abort_if(! $gatewayConfig, 404);

        return view('theme::payments.paddle-checkout', [
            'payment' => $payment,
            'transactionId' => $transactionId,
            'sandbox' => (string) $gatewayConfig->config('sandbox', '0') === '1',
            'clientSideToken' => $gatewayConfig->config('client_side_token'),
            'callbackUrl' => route('payments.gateway.callback', [
                'webhook_id' => $gatewayConfig->webhook_id,
                'payment_token' => $payment->token,
            ]),
        ]);
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
                // the column is a VARCHAR(255); a long exception message must not break the error log itself
                'message' => Str::limit('Webhook error ['.get_class($error).']: '.$error->getMessage(), 250),
            ]);

            // Details stay in the webhook log above; callers only learn that it failed.
            return response()->json([
                'success' => false,
                'code' => 500,
                'message' => 'The webhook could not be processed.',
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
            Log::error('Gateway callback failed', ['gateway_config_id' => $gatewayConfig->id, 'error' => $error->getMessage()]);

            return response()->json([
                'success' => false,
                'code' => 500,
                'message' => 'The payment could not be confirmed. Please contact support if you were charged.',
            ], 500);
        }
    }
}
