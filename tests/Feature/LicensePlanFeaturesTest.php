<?php

namespace Tests\Feature;

use App\Actions\AuthActions;
use App\Actions\OrderActions;
use App\Actions\RoleActions;
use App\Models\User;
use App\Support\LicensePlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Plan limits and features come from the license server (see SyncRuntimeMiddleware) and are stored
 * in the "lcs_plan_data" setting.
 */
class LicensePlanFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $data): void
    {
        settings(['encrypted:lcs_plan_data' => [$data]]);
    }

    private const HOBBY = [
        'staff_accounts_limit' => 1,
        'max_orders_limit' => 25,
        'max_gateways_limit' => 2,
        'max_servers_limit' => 2,
        'max_users_limit' => 500,
        'features' => ['subscriptions' => false, 'roles_permissions' => false, 'white_label' => false],
    ];

    public function test_licenses_without_feature_data_keep_every_feature(): void
    {
        $this->plan(['max_orders_limit' => 500]); // validated by a license server that predates features

        $this->assertTrue(LicensePlanLimits::subscriptionsEnabled());
        $this->assertTrue(LicensePlanLimits::rolesEnabled());
        $this->assertTrue(LicensePlanLimits::hasFeature(LicensePlanLimits::FEATURE_WHITE_LABEL));
        $this->assertNull(LicensePlanLimits::maxUsersLimit());
    }

    public function test_hobby_licenses_cannot_start_subscriptions_or_manage_roles(): void
    {
        $this->plan(self::HOBBY);

        $this->assertFalse(LicensePlanLimits::subscriptionsEnabled());
        $this->assertFalse(LicensePlanLimits::rolesEnabled());

        $this->assertRejected(fn () => OrderActions::createSubscriptionAsClient(['order_id' => 1, 'gateway_config_id' => 1, 'user_id' => 1]), 'order_id');
        $this->assertRejected(fn () => RoleActions::createRoleAsAdmin(['name' => 'Support']), 'role_id');
        $this->assertRejected(fn () => RoleActions::updateRoleAsAdmin(['role_id' => 1]), 'role_id');
        $this->assertRejected(fn () => RoleActions::assignRoleAsAdmin(['role_id' => 1, 'user_id' => 2]), 'role_id');
    }

    public function test_the_powered_by_credit_can_only_be_hidden_when_the_plan_allows_it(): void
    {
        settings(['hide_powered_by' => true]);

        $this->plan(self::HOBBY);
        $this->assertTrue(LicensePlanLimits::showPoweredBy(), 'Hobby keeps the credit even if the setting is on');

        $this->plan(['features' => ['white_label' => true]]);
        $this->assertFalse(LicensePlanLimits::showPoweredBy());

        settings(['hide_powered_by' => false]);
        $this->assertTrue(LicensePlanLimits::showPoweredBy(), 'shown until an admin hides it');
    }

    public function test_customer_sign_ups_stop_at_the_customer_accounts_limit(): void
    {
        $this->plan(['max_users_limit' => 2]);

        User::factory()->create(); // the initial admin (id 1) isn't a customer
        $this->register('aliceone');
        $this->register('bobbytwo');
        $this->assertSame(2, LicensePlanLimits::customerAccountsCount());

        $this->assertRejected(fn () => $this->register('carolthree'), 'email');
        $this->assertSame(2, LicensePlanLimits::customerAccountsCount());

        // raising the limit (or an unlimited plan) lets sign-ups continue
        $this->plan(['max_users_limit' => null]);
        $this->register('carolthree');
        $this->assertSame(3, LicensePlanLimits::customerAccountsCount());
    }

    private function register(string $username): User
    {
        return (new AuthActions)->registerAsClient([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'username' => $username,
            'email' => $username.'@example.com',
            'password' => 'correct-horse',
            'password_confirmation' => 'correct-horse',
        ]);
    }

    private function assertRejected(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail("Expected a validation error on {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
