<?php

namespace Tests\Feature;

use App\Http\Middleware\InstallAppMiddleware;
use App\Http\Middleware\SyncRuntimeMiddleware;
use App\Models\Extension;
use App\Models\GatewayConfig;
use App\Models\Payment;
use App\Models\User;
use Extensions\Gateways\LitePay\Gateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LitePayGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected GatewayConfig $gatewayConfig;

    protected Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::preventStrayRequests();
        $this->withoutMiddleware([InstallAppMiddleware::class, SyncRuntimeMiddleware::class]);

        Extension::query()->updateOrCreate(['namespace' => Gateway::class], [
            'identifier' => 'gateway-litepay',
            'type' => 'gateway',
            'name' => 'LitePay',
            'status' => 'enabled',
            'version' => '1.0.0',
        ]);

        $this->gatewayConfig = GatewayConfig::query()->create([
            'extension_identifier' => 'gateway-litepay',
            'webhook_id' => 'gateway-litepay-hook',
            'display_name' => 'LitePay',
            'type' => 'payment',
            'namespace' => Gateway::class,
            'config' => ['vendor_id' => 'VENDOR', 'secret' => 'SECRET'],
            'is_active' => true,
        ]);

        $user = User::factory()->create(['first_name' => 'Asha', 'email' => 'asha@example.com']);

        $this->payment = Payment::query()->create([
            'user_id' => $user->id,
            'gateway_config_id' => $this->gatewayConfig->id,
            'description' => 'Hosting order',
            'currency' => 'EUR',
            'total' => 25,
            'success_url' => 'https://wemx.test/success',
            'cancel_url' => 'https://wemx.test/cancel',
        ]);
    }

    public function test_pay_creates_a_litepay_payment_and_redirects(): void
    {
        Http::fake(['https://litepay.ch/p/' => Http::response(['status' => 'success', 'url' => 'https://litepay.ch/invoice/abc'])]);

        $response = (new Gateway)->pay($this->payment, $this->gatewayConfig);

        $this->assertSame('https://litepay.ch/invoice/abc', $response->getTargetUrl());

        Http::assertSent(function (Request $request) {
            return $request['vendor'] === 'VENDOR'
                && $request['price'] === '25.00'
                && $request['currency'] === 'EUR'
                && $request['invoice'] === (string) $this->payment->id
                && str_contains($request['callbackUrl'], 'signature=')
                && ! str_contains($request['callbackUrl'], 'SECRET');
        });
    }

    public function test_pay_surfaces_litepay_errors(): void
    {
        Http::fake(['https://litepay.ch/p/' => Http::response(['status' => 'error', 'message' => 'Invalid vendor'])]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid vendor');

        (new Gateway)->pay($this->payment, $this->gatewayConfig);
    }

    public function test_signed_webhook_completes_the_payment(): void
    {
        $url = $this->payment->webhookUrl(['signature' => (new Gateway)->signature($this->payment, $this->gatewayConfig)]);

        $this->get($url)->assertOk()->assertSee('*ok*');

        $this->assertTrue($this->payment->fresh()->isPaid());
    }

    public function test_unsigned_webhook_is_rejected(): void
    {
        $this->get($this->payment->webhookUrl(['signature' => 'forged']))->assertStatus(403);

        $this->assertFalse($this->payment->fresh()->isPaid());
    }

    public function test_return_redirects_to_the_success_page(): void
    {
        $this->get($this->payment->callbackUrl())->assertRedirect('https://wemx.test/success');

        $this->assertFalse($this->payment->fresh()->isPaid());
    }
}
