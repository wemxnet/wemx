<?php

namespace App\Support;

use App\Models\GatewayConfig;
use App\Models\Order;
use App\Models\ServerConnection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class LicensePlanLimits
{
    public static function staffAccountsLimit(): ?int
    {
        return self::positiveIntPlanLimit('staff_accounts_limit');
    }

    public static function maxOrdersLimit(): ?int
    {
        return self::positiveIntPlanLimit('max_orders_limit');
    }

    public static function maxServersLimit(): ?int
    {
        return self::positiveIntPlanLimit('max_servers_limit');
    }

    public static function maxGatewaysLimit(): ?int
    {
        return self::positiveIntPlanLimit('max_gateways_limit');
    }

    public static function maxUsersLimit(): ?int
    {
        return self::positiveIntPlanLimit('max_users_limit');
    }

    /** Plan features the license server can switch off. */
    public const FEATURE_SUBSCRIPTIONS = 'subscriptions';

    public const FEATURE_ROLES_PERMISSIONS = 'roles_permissions';

    public const FEATURE_WHITE_LABEL = 'white_label';

    /**
     * Whether the license includes a plan feature.
     *
     * Licenses validated before features existed (and installs that haven't validated yet) carry no
     * "features" data; those keep every feature, so an older license server never switches features off.
     */
    public static function hasFeature(string $feature): bool
    {
        $features = data_get(self::normalizedPlanArray(), 'features');

        if (! is_array($features) || ! array_key_exists($feature, $features)) {
            return true;
        }

        return filter_var($features[$feature], FILTER_VALIDATE_BOOLEAN);
    }

    public static function subscriptionsEnabled(): bool
    {
        return self::hasFeature(self::FEATURE_SUBSCRIPTIONS);
    }

    public static function rolesEnabled(): bool
    {
        return self::hasFeature(self::FEATURE_ROLES_PERMISSIONS);
    }

    /**
     * Whether the "Powered by WemX" credit is shown: always, unless the plan allows removing it and an admin has.
     */
    public static function showPoweredBy(): bool
    {
        return ! (self::hasFeature(self::FEATURE_WHITE_LABEL) && filter_var(settings('hide_powered_by', false), FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * Customer accounts: every user except the initial admin (id 1) and users with a staff role.
     */
    public static function customerAccountsCount(): int
    {
        return User::query()
            ->where('id', '!=', 1)
            ->whereDoesntHave('roles')
            ->count();
    }

    /**
     * @throws ValidationException
     */
    public static function assertCanCreateUsers(int $usersToCreate = 1, string $validationAttribute = 'email'): void
    {
        if ($usersToCreate < 1) {
            return;
        }

        $limit = self::maxUsersLimit();
        if ($limit === null) {
            return;
        }

        if (self::customerAccountsCount() + $usersToCreate > $limit) {
            throw ValidationException::withMessages([
                $validationAttribute => [
                    sprintf(
                        'This store has reached its limit of %d customer accounts, so new accounts can\'t be created right now. Please contact the store owner.',
                        $limit
                    ),
                ],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public static function assertFeature(string $feature, string $validationAttribute, string $message): void
    {
        if (! self::hasFeature($feature)) {
            throw ValidationException::withMessages([$validationAttribute => [$message]]);
        }
    }

    public static function serverConnectionsCount(): int
    {
        return ServerConnection::query()->count();
    }

    public static function gatewayConfigsCount(): int
    {
        return GatewayConfig::query()->count();
    }

    /**
     * Orders that are not terminated (pending through suspended remain provisioned).
     */
    public static function nonTerminatedOrdersCount(): int
    {
        return Order::query()->where('status', '!=', 'terminated')->count();
    }

    /**
     * @throws ValidationException
     */
    public static function assertCanCreateOrders(int $ordersToCreate, string $validationAttribute = 'order'): void
    {
        if ($ordersToCreate < 1) {
            return;
        }

        $limit = self::maxOrdersLimit();
        if ($limit === null) {
            return;
        }

        $current = self::nonTerminatedOrdersCount();
        if ($current + $ordersToCreate > $limit) {
            throw ValidationException::withMessages([
                $validationAttribute => [
                    sprintf(
                        'Your license allows %d active order(s). Terminate orders you no longer need, or upgrade your license.',
                        $limit
                    ),
                ],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public static function assertCanCreateServerConnections(int $connectionsToCreate = 1, string $validationAttribute = 'alias'): void
    {
        if ($connectionsToCreate < 1) {
            return;
        }

        $limit = self::maxServersLimit();
        if ($limit === null) {
            return;
        }

        $current = self::serverConnectionsCount();
        if ($current + $connectionsToCreate > $limit) {
            throw ValidationException::withMessages([
                $validationAttribute => [
                    sprintf(
                        'Your license allows %d server connection(s). Remove a connection you no longer need, or upgrade your license.',
                        $limit
                    ),
                ],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public static function assertCanCreateGatewayConfigs(int $configsToCreate = 1, string $validationAttribute = 'display_name'): void
    {
        if ($configsToCreate < 1) {
            return;
        }

        $limit = self::maxGatewaysLimit();
        if ($limit === null) {
            return;
        }

        $current = self::gatewayConfigsCount();
        if ($current + $configsToCreate > $limit) {
            throw ValidationException::withMessages([
                $validationAttribute => [
                    sprintf(
                        'Your license allows %d payment gateway configuration(s). Remove a gateway you no longer need, or upgrade your license.',
                        $limit
                    ),
                ],
            ]);
        }
    }

    private static function positiveIntPlanLimit(string $key): ?int
    {
        $plan = self::normalizedPlanArray();
        if ($plan === []) {
            return null;
        }

        $limit = data_get($plan, $key);
        if (($limit === null || $limit === '') && ! str_contains($key, '.')) {
            $limit = data_get($plan, 'limits.'.$key);
        }

        if ($limit === null || $limit === '') {
            return null;
        }

        return max(0, (int) $limit);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizedPlanArray(): array
    {
        $plan = settings('encrypted:lcs_plan_data');
        if ($plan === null) {
            return [];
        }

        if (is_string($plan)) {
            $decoded = json_decode($plan, true);
            $plan = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($plan)) {
            return [];
        }

        if ($plan !== [] && array_is_list($plan) && isset($plan[0]) && is_array($plan[0])) {
            return $plan[0];
        }

        return $plan;
    }
}
