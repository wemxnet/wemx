<?php

namespace Tests\Feature;

use App\Http\Middleware\InstallAppMiddleware;
use App\Http\Middleware\SyncRuntimeMiddleware;
use App\Models\Extension;
use App\Models\GatewayConfig;
use App\Models\Payment;
use App\Models\User;
use Extensions\Gateways\PayU\Gateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PayUGatewayTest extends TestCase
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
            'identifier' => 'gateway-payu',
            'type' => 'gateway',
            'name' => 'PayU',
            'status' => 'enabled',
            'version' => '1.0.0',
        ]);

        $this->gatewayConfig = GatewayConfig::query()->create([
            'extension_identifier' => 'gateway-payu',
            'webhook_id' => 'payu-hook',
            'display_name' => 'PayU',
            'type' => 'payment',
            'namespace' => Gateway::class,
            'config' => ['merchant_key' => 'KEY', 'merchant_salt' => 'SALT', 'test_mode' => '1'],
            'is_active' => true,
        ]);

        $user = User::factory()->create(['first_name' => 'Asha', 'email' => 'asha@example.com']);

        $this->payment = Payment::query()->create([
            'user_id' => $user->id,
            'gateway_config_id' => $this->gatewayConfig->id,
            'description' => 'Hosting order',
            'currency' => 'INR',
            'total' => 499,
            'success_url' => 'https://wemx.test/success',
            'cancel_url' => 'https://wemx.test/cancel',
        ]);
    }

    public function test_only_inr_is_supported(): void
    {
        $this->assertSame(['INR'], (new Gateway)->getSupportedCurrencies());
    }

    public function test_pay_renders_a_signed_form_for_payu(): void
    {
        $response = (new Gateway)->pay($this->payment, $this->gatewayConfig);
        $html = $response->getContent();
        $transactionId = $this->payment->fresh()->transaction_id;

        $this->assertStringStartsWith('wx'.$this->payment->id.'x', $transactionId);
        $this->assertStringContainsString('action="https://test.payu.in/_payment"', $html);
        $this->assertStringContainsString('name="amount" value="499.00"', $html);
        $this->assertStringContainsString('name="firstname" value="Asha"', $html);

        $expected = hash('sha512', "KEY|{$transactionId}|499.00|Hosting order|Asha|asha@example.com|||||||||||SALT");
        $this->assertStringContainsString('name="hash" value="'.$expected.'"', $html);
    }

    public function test_successful_return_completes_the_payment(): void
    {
        $this->fakeVerify('success', '499.00');

        $this->post($this->callbackUrl(), $this->payuResponse('success'))
            ->assertRedirect('https://wemx.test/success');

        $this->assertTrue($this->payment->fresh()->isPaid());
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://test.payu.in/merchant/postservice') && $request['command'] === 'verify_payment');
    }

    public function test_tampered_hash_is_rejected(): void
    {
        $this->fakeVerify('success', '499.00');

        $this->post($this->callbackUrl(), array_merge($this->payuResponse('success'), ['hash' => 'forged']))
            ->assertStatus(500);

        $this->assertFalse($this->payment->fresh()->isPaid());
        Http::assertNothingSent();
    }

    public function test_amount_mismatch_is_not_completed(): void
    {
        $this->fakeVerify('success', '1.00');

        $this->post($this->callbackUrl(), $this->payuResponse('success'))
            ->assertRedirect('https://wemx.test/cancel');

        $this->assertFalse($this->payment->fresh()->isPaid());
    }

    public function test_failed_payment_returns_to_cancel_url(): void
    {
        $this->fakeVerify('failure', '499.00');

        $this->post($this->callbackUrl(), $this->payuResponse('failure'))
            ->assertRedirect('https://wemx.test/cancel');

        $this->assertFalse($this->payment->fresh()->isPaid());
    }

    protected function callbackUrl(): string
    {
        $this->payment->update(['transaction_id' => 'wx'.$this->payment->id.'xabc']);

        return $this->payment->fresh()->callbackUrl();
    }

    /**
     * @return array<string, string>
     */
    protected function payuResponse(string $status): array
    {
        $response = [
            'status' => $status,
            'txnid' => 'wx'.$this->payment->id.'xabc',
            'amount' => '499.00',
            'productinfo' => 'Hosting order',
            'firstname' => 'Asha',
            'email' => 'asha@example.com',
            'mihpayid' => '403993715',
        ];

        $response['hash'] = (new Gateway)->responseHash($response, $this->gatewayConfig);

        return $response;
    }

    protected function fakeVerify(string $status, string $amount): void
    {
        Http::fake(fn () => Http::response([
            'status' => 1,
            'transaction_details' => [
                'wx'.$this->payment->id.'xabc' => ['status' => $status, 'amt' => $amount, 'mihpayid' => '403993715'],
            ],
        ]));
    }
}
