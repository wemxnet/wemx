<?php

namespace Tests\Feature;

use App\Http\Middleware\InstallAppMiddleware;
use App\Http\Middleware\SyncRuntimeMiddleware;
use App\Models\Currency;
use App\Models\Extension;
use App\Models\GatewayConfig;
use App\Models\Payment;
use App\Models\User;
use Extensions\Gateways\Xendit\Gateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class XenditGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected GatewayConfig $gatewayConfig;

    protected Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['USD' => 1, 'IDR' => 16000] as $code => $rate) {
            Currency::query()->firstOrCreate(['currency' => $code], [
                'display_name' => $code,
                'format' => $code.' 1,0.00',
                'market_rate' => $rate,
                'is_active' => true,
            ]);
        }

        Queue::fake();
        Http::preventStrayRequests();
        $this->withoutMiddleware([InstallAppMiddleware::class, SyncRuntimeMiddleware::class]);

        Extension::query()->updateOrCreate(['namespace' => Gateway::class], [
            'identifier' => 'gateway-xendit',
            'type' => 'gateway',
            'name' => 'Xendit',
            'status' => 'enabled',
            'version' => '1.0.0',
        ]);

        $this->gatewayConfig = GatewayConfig::query()->create([
            'extension_identifier' => 'gateway-xendit',
            'webhook_id' => 'gateway-xendit-hook',
            'display_name' => 'Xendit',
            'type' => 'payment',
            'namespace' => Gateway::class,
            'config' => ['secret_key' => 'xnd_development_KEY', 'callback_token' => 'CBTOKEN'],
            'is_active' => true,
        ]);

        $user = User::factory()->create(['first_name' => 'Asha', 'email' => 'asha@example.com']);

        $this->payment = Payment::query()->create([
            'user_id' => $user->id,
            'gateway_config_id' => $this->gatewayConfig->id,
            'description' => 'Hosting order',
            'currency' => 'IDR',
            'total' => 150000,
            'success_url' => 'https://wemx.test/success',
            'cancel_url' => 'https://wemx.test/cancel',
        ]);
    }

    public function test_pay_creates_an_invoice_and_redirects(): void
    {
        Http::fake(['https://api.xendit.co/v2/invoices' => Http::response(['id' => 'inv_1', 'invoice_url' => 'https://checkout.xendit.co/web/inv_1', 'status' => 'PENDING'])]);

        $response = (new Gateway)->pay($this->payment, $this->gatewayConfig);

        $this->assertSame('https://checkout.xendit.co/web/inv_1', $response->getTargetUrl());
        $this->assertSame('inv_1', $this->payment->fresh()->transaction_id);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('xnd_development_KEY:'))
            && $request['external_id'] === 'wemx-'.$this->payment->token
            && $request['amount'] == 150000
            && $request['currency'] === 'IDR'
            && $request['payer_email'] === 'asha@example.com'
            && $request['success_redirect_url'] === $this->payment->callbackUrl());
    }

    public function test_webhook_with_a_bad_token_is_rejected(): void
    {
        $this->postJson(route('payments.gateway.webhook', ['webhook_id' => 'gateway-xendit-hook']), $this->webhookBody(), ['x-callback-token' => 'nope'])
            ->assertStatus(403);

        $this->assertFalse($this->payment->fresh()->isPaid());
    }

    public function test_webhook_completes_a_paid_invoice_after_re_reading_it(): void
    {
        $this->fakeInvoice('PAID', 150000);

        $this->postJson(route('payments.gateway.webhook', ['webhook_id' => 'gateway-xendit-hook']), $this->webhookBody(), ['x-callback-token' => 'CBTOKEN'])
            ->assertOk()
            ->assertJson(['message' => 'Payment completed']);

        $this->assertTrue($this->payment->fresh()->isPaid());
        Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->url() === 'https://api.xendit.co/v2/invoices/inv_1');
    }

    public function test_webhook_ignores_a_forged_paid_status(): void
    {
        $this->fakeInvoice('PENDING', 150000);

        $this->postJson(route('payments.gateway.webhook', ['webhook_id' => 'gateway-xendit-hook']), $this->webhookBody(), ['x-callback-token' => 'CBTOKEN'])
            ->assertOk();

        $this->assertFalse($this->payment->fresh()->isPaid());
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        $this->fakeInvoice('PAID', 1000);

        $this->postJson(route('payments.gateway.webhook', ['webhook_id' => 'gateway-xendit-hook']), $this->webhookBody(), ['x-callback-token' => 'CBTOKEN'])
            ->assertStatus(500);

        $this->assertFalse($this->payment->fresh()->isPaid());
    }

    public function test_return_from_checkout_completes_a_paid_invoice(): void
    {
        $this->fakeInvoice('SETTLED', 150000);
        $this->payment->update(['transaction_id' => 'inv_1']);

        $this->get($this->payment->fresh()->callbackUrl())->assertRedirect('https://wemx.test/success');

        $this->assertTrue($this->payment->fresh()->isPaid());
    }

    /**
     * @return array<string, mixed>
     */
    protected function webhookBody(): array
    {
        return ['id' => 'inv_1', 'external_id' => 'wemx-'.$this->payment->token, 'status' => 'PAID', 'amount' => 150000];
    }

    protected function fakeInvoice(string $status, int $amount): void
    {
        Http::fake(['https://api.xendit.co/v2/invoices/inv_1' => Http::response([
            'id' => 'inv_1',
            'external_id' => 'wemx-'.$this->payment->token,
            'status' => $status,
            'amount' => $amount,
            'currency' => 'IDR',
        ])]);
    }
}
