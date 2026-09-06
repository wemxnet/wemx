<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\DataExport\DataExportFilters;
use App\Services\DataExport\DataExportRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.installed' => true,
            'app.license_key' => 'WMX-TESTING-KEY',
        ]);

        // Skips the outbound license validation call in SyncRuntimeMiddleware.
        Cache::put('lcs_checked_at', now(), 21600);
    }

    public function test_dashboard_lists_the_available_datasets(): void
    {
        $this->actingAsPrimaryAdmin();

        $this->get(route('admin.data-export.index'))
            ->assertOk()
            ->assertSee('Invoices &amp; payments', false)
            ->assertSee('Revenue by month')
            ->assertSee('Renewal forecast');
    }

    public function test_customers_without_staff_access_cannot_open_the_dashboard(): void
    {
        $this->actingAsPrimaryAdmin();

        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('admin.data-export.index'))
            ->assertForbidden();
    }

    public function test_staff_without_the_export_permission_cannot_open_the_dashboard(): void
    {
        $this->actingAsPrimaryAdmin();

        $this->actingAsStaff(['admin.payments'])
            ->get(route('admin.data-export.index'))
            ->assertForbidden();
    }

    public function test_invoices_export_streams_csv_rows(): void
    {
        $this->actingAsPrimaryAdmin();
        $this->seedBillingData();

        $response = $this->get(route('admin.data-export.download', ['dataset' => 'invoices']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('.csv', $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        $this->assertStringContainsString('"Invoice ID",Status,"Invoice date"', $lines[0]);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Excel needs a UTF-8 byte order mark.');
        $this->assertStringContainsString('INV-0001', $csv);
        $this->assertStringContainsString('ada@example.test', $csv);
        $this->assertStringContainsString('12.00', $csv);
        $this->assertCount(3, $lines, 'Expected a header row plus the two seeded invoices.');
    }

    public function test_export_can_be_streamed_as_json(): void
    {
        $this->actingAsPrimaryAdmin();
        $this->seedBillingData();

        $response = $this->get(route('admin.data-export.download', [
            'dataset' => 'invoices',
            'format' => 'json',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');

        $rows = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(2, $rows);
        $this->assertSame('INV-0002', $rows[0]['invoice_id']);
        $this->assertSame('unpaid', $rows[0]['status']);
        $this->assertSame('Stripe', $rows[0]['gateway']);
    }

    public function test_date_range_narrows_the_exported_rows(): void
    {
        $this->actingAsPrimaryAdmin();
        $this->seedBillingData();

        $response = $this->get(route('admin.data-export.download', [
            'dataset' => 'invoices',
            'from' => now()->subDays(2)->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $csv = $response->assertOk()->streamedContent();

        $this->assertStringContainsString('INV-0001', $csv);
        $this->assertStringNotContainsString('INV-0002', $csv, 'The 45 day old invoice should fall outside the range.');
    }

    public function test_row_limit_caps_the_exported_rows(): void
    {
        $this->actingAsPrimaryAdmin();
        $this->seedBillingData();

        $csv = $this->get(route('admin.data-export.download', [
            'dataset' => 'invoices',
            'limit' => 1,
        ]))->assertOk()->streamedContent();

        $lines = array_values(array_filter(explode("\n", trim($csv))));

        $this->assertCount(2, $lines, 'Expected a header row plus a single invoice.');
    }

    public function test_revenue_by_month_aggregates_paid_invoices(): void
    {
        $this->actingAsPrimaryAdmin();
        $this->seedBillingData();

        $rows = json_decode(
            $this->get(route('admin.data-export.download', [
                'dataset' => 'revenue_by_month',
                'format' => 'json',
            ]))->assertOk()->streamedContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertCount(1, $rows);
        $this->assertSame(now()->format('Y-m'), $rows[0]['month']);
        $this->assertSame('USD', $rows[0]['currency']);
        $this->assertSame('1', $rows[0]['invoices']);
        $this->assertSame('12.00', $rows[0]['gross_total']);
        $this->assertSame('11.50', $rows[0]['net_earnings']);
    }

    public function test_unknown_datasets_return_not_found(): void
    {
        $this->actingAsPrimaryAdmin();

        $this->get(route('admin.data-export.download', ['dataset' => 'nonsense']))
            ->assertNotFound();
    }

    public function test_staff_cannot_export_a_dataset_they_lack_the_resource_permission_for(): void
    {
        $this->actingAsPrimaryAdmin();

        $staff = $this->actingAsStaff(['admin.data-export', 'admin.orders']);

        $staff->get(route('admin.data-export.download', ['dataset' => 'orders']))->assertOk();
        $staff->get(route('admin.data-export.download', ['dataset' => 'invoices']))->assertForbidden();
    }

    public function test_downloads_are_written_to_the_audit_trail(): void
    {
        $admin = $this->actingAsPrimaryAdmin();

        $this->get(route('admin.data-export.download', ['dataset' => 'customers']))->assertOk();

        $log = DB::table('activity_logs')->where('event', 'data_export')->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertStringContainsString('Customer list', $log->description);
        $this->assertStringContainsString('"dataset":"customers"', $log->properties);
    }

    public function test_dashboard_component_exposes_datasets_and_switches_selection(): void
    {
        $this->actingAsPrimaryAdmin();
        $this->seedBillingData();

        Volt::test(admin_view_path('data-export.livewire.export-dashboard'))
            ->assertSet('dataset', 'invoices')
            ->assertSee('Invoices')
            ->call('selectDataset', 'upcoming_renewals')
            ->assertSet('dataset', 'upcoming_renewals')
            ->assertSee('Renewal forecast')
            ->call('selectDataset', 'not-a-dataset')
            ->assertSet('dataset', 'upcoming_renewals')
            ->set('search', 'churn')
            ->assertSee('Churn & cancellations')
            ->assertDontSee('Sent email log');
    }

    public function test_dashboard_component_applies_presets_and_row_limits(): void
    {
        $this->actingAsPrimaryAdmin();
        $this->seedBillingData();

        Volt::test(admin_view_path('data-export.livewire.export-dashboard'))
            ->set('dataset', 'invoices')
            ->assertSee('rows will be exported')
            ->set('limit', '1')
            ->assertSee('row will be exported')
            ->assertSee('limited from 2')
            ->set('limit', null)
            ->call('applyPreset', 'this_month')
            ->assertSet('from', now()->startOfMonth()->toDateString())
            ->assertSet('to', now()->endOfMonth()->toDateString())
            ->assertSee('row will be exported')
            ->assertDontSee('limited from')
            ->call('applyPreset', 'all_time')
            ->assertSet('from', null)
            ->assertSee('rows will be exported');
    }

    /**
     * Guards against typos in the hand written SQL by running every dataset and
     * asserting the mapped rows match the columns the dataset advertises.
     */
    public function test_every_dataset_executes_and_returns_its_declared_columns(): void
    {
        $this->actingAsPrimaryAdmin();
        $this->seedBillingData();

        $definitions = app(DataExportRegistry::class)->all();
        $filters = new DataExportFilters;

        $this->assertGreaterThanOrEqual(20, count($definitions));

        foreach ($definitions as $key => $definition) {
            $this->assertNotEmpty($definition->columns, "Dataset [{$key}] declares no columns.");
            $this->assertGreaterThanOrEqual(0, $definition->count($filters), "Dataset [{$key}] could not be counted.");

            foreach ($definition->rows($filters->limitedTo(5)) as $row) {
                $this->assertSame(
                    $definition->columnKeys(),
                    array_keys($row),
                    "Dataset [{$key}] returned columns that do not match its definition.",
                );
            }
        }
    }

    public function test_date_filtered_datasets_are_countable_within_a_range(): void
    {
        $this->actingAsPrimaryAdmin();
        $this->seedBillingData();

        $filters = DataExportFilters::fromArray([
            'from' => now()->subYear()->toDateString(),
            'to' => now()->toDateString(),
        ]);

        foreach (app(DataExportRegistry::class)->all() as $key => $definition) {
            if (! $definition->supportsDateRange()) {
                continue;
            }

            $this->assertGreaterThanOrEqual(
                0,
                $definition->count($filters),
                "Dataset [{$key}] failed when filtered by date.",
            );
        }
    }

    protected function actingAsPrimaryAdmin(): User
    {
        $admin = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->assertTrue($admin->isPrimaryAdmin());

        $this->actingAs($admin)->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()]);

        return $admin;
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function actingAsStaff(array $permissions): self
    {
        $staff = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $role = Role::create(['name' => 'Support '.$staff->id, 'super_admin' => false]);

        foreach ($permissions as $permission) {
            DB::table('role_permissions')->insert([
                'role_id' => $role->id,
                'permission' => $permission,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('role_user')->insert([
            'role_id' => $role->id,
            'user_id' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->actingAs($staff->fresh())
            ->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()]);
    }

    protected function seedBillingData(): void
    {
        $now = now();

        $customer = User::factory()->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'status' => 'active',
            'balance' => 25,
            'country' => 'GB',
        ]);

        $connectionId = DB::table('server_connections')->insertGetId([
            'extension_identifier' => 'pterodactyl',
            'alias' => 'Main panel',
            'status' => 'online',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $categoryId = DB::table('categories')->insertGetId([
            'status' => 'active',
            'name' => 'Game servers',
            'slug' => 'game-servers',
            'icon' => 'default.png',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $packageId = DB::table('packages')->insertGetId([
            'category_id' => $categoryId,
            'connection_id' => $connectionId,
            'slug' => 'starter',
            'name' => 'Starter',
            'icon' => 'default.png',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $priceId = DB::table('package_prices')->insertGetId([
            'package_id' => $packageId,
            'short_description' => 'Monthly',
            'period_in_days' => 30,
            'price' => 10,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $gatewayId = DB::table('gateway_configs')->insertGetId([
            'extension_identifier' => 'gateway-stripe',
            'display_name' => 'Stripe',
            'namespace' => 'Extensions\Gateways\Stripe\Gateway',
            'type' => 'payment',
            'is_active' => true,
            'is_staff_only' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'user_id' => $customer->id,
            'package_id' => $packageId,
            'package_price_id' => $priceId,
            'external_id' => 'srv-1001',
            'status' => 'active',
            'cycle_price' => 10,
            'period_in_days' => 30,
            'due_date' => $now->copy()->addDays(10),
            'last_renewed_at' => $now,
            'auto_balance_renew' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $paidPaymentId = DB::table('payments')->insertGetId([
            'token' => 'token-paid',
            'invoice_id' => 'INV-0001',
            'user_id' => $customer->id,
            'gateway_config_id' => $gatewayId,
            'payable_type' => Order::class,
            'payable_id' => $orderId,
            'transaction_id' => 'txn-paid',
            'status' => 'paid',
            'description' => 'Starter - Monthly',
            'currency' => 'USD',
            'subtotal' => 10,
            'discount' => 0,
            'tax' => 2,
            'total' => 12,
            'earnings' => 11.5,
            'paid_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('payments')->insert([
            'token' => 'token-unpaid',
            'invoice_id' => 'INV-0002',
            'user_id' => $customer->id,
            'gateway_config_id' => $gatewayId,
            'status' => 'unpaid',
            'description' => 'Starter renewal',
            'currency' => 'USD',
            'subtotal' => 10,
            'total' => 10,
            'earnings' => 10,
            'created_at' => $now->copy()->subDays(45),
            'updated_at' => $now->copy()->subDays(45),
        ]);

        DB::table('payment_tax_details')->insert([
            'payment_id' => $paidPaymentId,
            'company_name' => 'Analytical Engines Ltd',
            'tax_id' => 'GB123456789',
            'country' => 'GB',
            'region' => 'London',
            'city' => 'London',
            'zip_code' => 'EC1A',
            'tax_name' => 'VAT',
            'tax_rate' => 20,
            'tax_exempt' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('payment_refunds')->insert([
            'payment_id' => $paidPaymentId,
            'user_id' => $customer->id,
            'gateway_config_id' => $gatewayId,
            'transaction_id' => 'txn-refund',
            'amount' => 12,
            'currency' => 'USD',
            'reason' => 'Duplicate charge',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('subscriptions')->insert([
            'token' => 'sub-token',
            'user_id' => $customer->id,
            'gateway_config_id' => $gatewayId,
            'subscription_id' => 'sub_stripe_1',
            'subscribable_type' => Order::class,
            'subscribable_id' => $orderId,
            'status' => 'active',
            'description' => 'Starter - Monthly',
            'currency' => 'USD',
            'amount' => 12,
            'frequency' => 30,
            'activated_at' => $now,
            'last_checked_at' => $now,
            'next_billing_at' => $now->copy()->addDays(30),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('addresses')->insert([
            'user_id' => $customer->id,
            'company_name' => 'Analytical Engines Ltd',
            'tax_id' => 'GB123456789',
            'address' => '1 Difference Street',
            'country' => 'GB',
            'region' => 'London',
            'city' => 'London',
            'zip_code' => 'EC1A',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('balance_transactions')->insert([
            'user_id' => $customer->id,
            'result' => '+',
            'description' => 'Account top-up',
            'amount' => 25,
            'balance_before_transaction' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('cart_order_items')->insert([
            'basket_identifier' => 'basket-1',
            'user_id' => $customer->id,
            'cartable_type' => 'App\Models\PackagePrice',
            'cartable_id' => $priceId,
            'name' => 'Starter - Monthly',
            'price' => 10,
            'quantity' => 1,
            'is_paid' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('order_prices')->insert([
            'order_id' => $orderId,
            'cycle_price' => 2,
            'upgrade_fee' => 0,
            'description' => 'Extra 2GB memory',
            'type' => 'config_option',
            'key' => 'memory',
            'value' => '2048',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('server_accounts')->insert([
            'user_id' => $customer->id,
            'order_id' => $orderId,
            'server' => 'pterodactyl',
            'external_id' => 'panel-77',
            'username' => 'ada',
            'password' => 'encrypted-secret',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $taxCountryId = DB::table('sales_tax_countries')->insertGetId([
            'country_code' => 'GB',
            'sales_tax_name' => 'VAT',
            'sales_tax_rate' => 20,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('sales_tax_states')->insert([
            'country_id' => $taxCountryId,
            'state_code' => 'LDN',
            'sales_tax_name' => 'VAT',
            'sales_tax_rate' => 20,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('emails')->insert([
            'user_id' => $customer->id,
            'identifier' => 'payment.paid',
            'from' => 'billing@example.test',
            'to' => $customer->email,
            'subject' => 'Payment received',
            'lines' => json_encode(['Thanks for your payment.']),
            'theme' => 'default',
            'status' => 'sent',
            'display' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('activity_logs')->insert([
            'user_id' => $customer->id,
            'model_type' => Order::class,
            'model_id' => $orderId,
            'event' => 'updated',
            'description' => 'Order status changed',
            'tag' => 'orders',
            'field' => 'status',
            'old_value' => 'pending',
            'new_value' => 'active',
            'ip_address' => '127.0.0.1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('user_bans')->insert([
            'user_id' => $customer->id,
            'banned_by_id' => 1,
            'ip_address' => '127.0.0.1',
            'is_ip_ban' => false,
            'reason' => 'Chargeback abuse',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
