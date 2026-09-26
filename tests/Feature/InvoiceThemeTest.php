<?php

namespace Tests\Feature;

use App\Invoices\InvoiceTheme;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InvoiceThemeTest extends TestCase
{
    use RefreshDatabase;

    private ?string $temporaryTheme = null;

    protected function tearDown(): void
    {
        if ($this->temporaryTheme !== null) {
            File::deleteDirectory($this->temporaryTheme);
        }

        parent::tearDown();
    }

    public function test_installed_themes_are_discovered(): void
    {
        $themes = InvoiceTheme::all();

        $this->assertArrayHasKey('default', $themes);
        $this->assertSame('Default', $themes['default']->name);
        $this->assertSame('The built-in invoice PDF layout.', $themes['default']->description);
        $this->assertSame('invoice-theme-default', $themes['default']->namespace());
        $this->assertArrayHasKey('dark', $themes);
        $this->assertSame('Dark', $themes['dark']->name);
        $this->assertSame('A dark invoice PDF layout.', $themes['dark']->description);
    }

    public function test_folders_without_an_invoice_template_are_ignored(): void
    {
        $path = resource_path('invoices/unfinished');
        File::ensureDirectoryExists($path);
        File::put($path.'/readme.txt', 'not a theme');
        $this->temporaryTheme = $path;

        $this->assertArrayNotHasKey('unfinished', InvoiceTheme::all());
    }

    public function test_default_theme_renders_the_built_in_invoice(): void
    {
        $payment = $this->payment();

        $html = view(InvoiceTheme::default()->view(), InvoiceTheme::default()->viewData($payment))->render();

        $this->assertStringContainsString('Invoice', $html);
        $this->assertStringContainsString($payment->invoice_id, $html);
        $this->assertStringContainsString('Hosting invoice', $html);
        $this->assertStringContainsString('Billing From', $html);
        $this->assertStringContainsString('Payment Details', $html);
    }

    public function test_dark_theme_renders_a_dark_layout(): void
    {
        $payment = $this->payment();
        $theme = InvoiceTheme::find('dark');

        $html = view($theme->view(), $theme->viewData($payment))->render();

        $this->assertStringContainsString('#11111d', $html);
        $this->assertStringContainsString('#171725', $html);
        $this->assertStringContainsString('#32324a', $html);
        $this->assertStringContainsString('Invoice', $html);
        $this->assertStringContainsString($payment->invoice_id, $html);
        $this->assertStringContainsString('Hosting invoice', $html);
        $this->assertStringContainsString('Billing From', $html);
        $this->assertStringContainsString('Payment Details', $html);
    }

    public function test_configured_theme_is_used_when_rendering(): void
    {
        $this->installTemporaryTheme();
        settings(['invoice_theme' => 'accent']);

        $payment = $this->payment();
        $theme = InvoiceTheme::default();

        $html = view($theme->view(), $theme->viewData($payment))->render();

        $this->assertSame('accent', $theme->slug);
        $this->assertStringContainsString('id="accent-invoice"', $html);
        $this->assertStringContainsString('Hosting invoice', $html);
        $this->assertStringContainsString($payment->invoice_id, $html);
    }

    public function test_a_missing_theme_falls_back_to_the_built_in_layout(): void
    {
        $this->installTemporaryTheme();
        settings(['invoice_theme' => 'accent']);

        File::deleteDirectory($this->temporaryTheme);
        $this->temporaryTheme = null;

        $this->assertSame('default', InvoiceTheme::resolve('accent')->slug);
        $this->assertSame('default', InvoiceTheme::default()->slug);
    }

    public function test_invoicing_settings_reject_an_unknown_invoice_theme(): void
    {
        try {
            Setting::actions()->updateApplicationInvoicingSettingsAsAdmin([
                'invoice_format' => 'INV-{year}-{id}',
                'invoice_id_padding' => 4,
                'invoice_theme' => 'not-installed',
            ]);
            $this->fail('An unknown invoice theme should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice_theme', $exception->errors());
        }
    }

    public function test_admin_can_set_the_default_invoice_theme(): void
    {
        $this->installTemporaryTheme();

        $admin = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin);

        Volt::test('admin_area.default.settings.livewire.invoicing')
            ->assertSee('Default Invoice Theme')
            ->assertSee('Accent')
            ->set('invoice_theme', 'accent')
            ->call('saveChanges')
            ->assertHasNoErrors();

        $this->assertSame('accent', settings('invoice_theme'));
    }

    public function test_invoicing_settings_page_lists_invoice_themes(): void
    {
        $this->installTemporaryTheme();
        $this->allowAdminRequests();

        $admin = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()])
            ->get(route('admin.settings.index', ['page' => 'invoicing']))
            ->assertOk()
            ->assertSee('Default Invoice Theme')
            ->assertSee('Accent');
    }

    public function test_admin_invoice_download_uses_the_configured_theme(): void
    {
        $this->installTemporaryTheme();
        settings(['invoice_theme' => 'accent']);
        $this->allowAdminRequests();

        $admin = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $payment = $this->payment($admin);

        $this->expectThemePdf('invoice-theme-accent::invoice', $payment);

        $this->actingAs($admin)
            ->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()])
            ->get(route('admin.payments.invoice-pdf', ['payment' => $payment->id]))
            ->assertOk()
            ->assertSee('pdf-invoice-', false);
    }

    public function test_client_invoice_download_uses_the_configured_theme(): void
    {
        $this->installTemporaryTheme();
        settings([
            'invoice_theme' => 'accent',
            'allow_client_pdf_invoices' => true,
        ]);
        $this->allowAdminRequests();

        $customer = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $payment = $this->payment($customer);

        $this->expectThemePdf('invoice-theme-accent::invoice', $payment);

        $this->actingAs($customer)
            ->get(route('payments.view.invoice-pdf', ['payment' => $payment->token]))
            ->assertOk()
            ->assertSee('pdf-invoice-', false);
    }

    public function test_clients_cannot_download_invoices_when_pdfs_are_disabled(): void
    {
        $this->allowAdminRequests();
        settings(['allow_client_pdf_invoices' => false]);

        $customer = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $payment = $this->payment($customer);

        $this->actingAs($customer)
            ->get(route('payments.view.invoice-pdf', ['payment' => $payment->token]))
            ->assertForbidden();
    }

    private function expectThemePdf(string $view, Payment $payment): void
    {
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('download')
            ->once()
            ->andReturnUsing(fn (string $filename): Response => response('pdf-'.$filename, 200, [
                'Content-Type' => 'application/pdf',
            ]));

        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function (string $loadedView, array $data) use ($view, $payment) {
                return $loadedView === $view && $data['payment']->is($payment);
            })
            ->andReturn($pdf);
    }

    private function payment(?User $user = null): Payment
    {
        if (! Currency::query()->where('currency', 'USD')->exists()) {
            Currency::query()->create([
                'currency' => 'USD',
                'display_name' => 'US Dollar',
                'format' => '$1,0.00',
                'market_rate' => 1,
                'is_active' => true,
            ]);
        }

        return Payment::query()->create([
            'user_id' => $user?->id,
            'status' => 'paid',
            'description' => 'Hosting invoice',
            'currency' => 'USD',
            'subtotal' => 10,
            'tax' => 0,
            'discount' => 0,
            'total' => 10,
        ]);
    }

    private function allowAdminRequests(): void
    {
        config([
            'app.installed' => true,
            'app.license_key' => 'WMX-TESTING-KEY',
        ]);
        Cache::put('lcs_checked_at', now(), 21600);
    }

    private function installTemporaryTheme(): void
    {
        $path = resource_path('invoices/accent');
        File::ensureDirectoryExists($path);
        File::put($path.'/theme.json', json_encode([
            'name' => 'Accent',
            'description' => 'A temporary theme used by tests.',
        ]));
        File::put($path.'/invoice.blade.php', <<<'BLADE'
<div id="accent-invoice">{{ $payment->description }}</div>
<div>{{ $payment->invoice_id }}</div>
BLADE);
        $this->temporaryTheme = $path;
    }
}
