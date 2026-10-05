<?php

namespace Tests\Feature;

use App\Actions\AuthActions;
use App\Events\Orders\OrderRenewed;
use App\Events\Orders\OrderTransferred;
use App\Events\Orders\OrderUpgraded;
use App\Events\Subscriptions\SubscriptionActivated;
use App\Events\Subscriptions\SubscriptionCancelled;
use App\Events\Subscriptions\SubscriptionDeactivated;
use App\Events\Users\UserBalanceCredited;
use App\Events\Users\UserBanLifted;
use App\Events\Users\UserBanned;
use App\Events\Users\UserEmailVerified;
use App\Events\Users\UserLoggedIn;
use App\Events\Users\UserLoginFailed;
use App\Events\Users\UserPasswordChanged;
use App\Events\Users\UserPasswordReset;
use App\Events\Users\UserTwoFactorDisabled;
use App\Events\Users\UserTwoFactorEnabled;
use App\Http\Controllers\Client\AuthController;
use App\Models\Category;
use App\Models\Email;
use App\Models\Extension;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\PasswordResetToken;
use App\Models\ServerConnection;
use App\Models\Subscription;
use App\Models\User;
use Extensions\Servers\Universal\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class DomainEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_dispatches_success_and_failure_events(): void
    {
        $user = User::factory()->create();

        Event::fake([UserLoggedIn::class, UserLoginFailed::class]);

        app(AuthActions::class)->loginAsClient([
            'username' => $user->email,
            'password' => 'password',
        ]);

        Event::assertDispatched(UserLoggedIn::class, function (UserLoggedIn $event) use ($user) {
            return $event->user->is($user);
        });

        auth()->logout();

        try {
            app(AuthActions::class)->loginAsClient([
                'username' => $user->email,
                'password' => 'wrong-password',
            ]);
            $this->fail('Expected a validation exception for a failed login.');
        } catch (ValidationException) {
        }

        Event::assertDispatched(UserLoginFailed::class, function (UserLoginFailed $event) use ($user) {
            return $event->identifier === $user->email;
        });
    }

    public function test_password_reset_and_change_dispatch_events(): void
    {
        $user = User::factory()->create();

        PasswordResetToken::query()->create([
            'email' => $user->email,
            'token' => 'reset-token',
            'created_at' => now(),
        ]);

        Event::fake([UserPasswordReset::class, UserPasswordChanged::class]);

        app(AuthActions::class)->resetPasswordAsClient([
            'token' => 'reset-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        Event::assertDispatched(UserPasswordReset::class, function (UserPasswordReset $event) use ($user) {
            return $event->user->is($user);
        });

        User::actions()->updatePasswordAsClient([
            'user_id' => $user->id,
            'current_password' => 'new-password',
            'new_password' => 'newer-password',
            'new_password_confirmation' => 'newer-password',
        ]);

        Event::assertDispatched(UserPasswordChanged::class, function (UserPasswordChanged $event) use ($user) {
            return $event->user->is($user);
        });
    }

    public function test_email_verification_dispatches_once(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake([UserEmailVerified::class]);

        $user->markEmailAsVerified();
        $user->markEmailAsVerified();

        Event::assertDispatchedTimes(UserEmailVerified::class, 1);
    }

    public function test_two_factor_changes_dispatch_events(): void
    {
        $user = User::factory()->create();
        $secret = $user->generateTwoFactorSecret();
        $code = (new Google2FA)->getCurrentOtp($secret);

        Event::fake([UserTwoFactorEnabled::class, UserTwoFactorDisabled::class]);

        AuthActions::enableTwoFactorAuthAsClient([
            'user_id' => $user->id,
            'tfa_code' => $code,
        ]);

        Event::assertDispatched(UserTwoFactorEnabled::class, function (UserTwoFactorEnabled $event) use ($user) {
            return $event->user->is($user);
        });

        User::actions()->disableTwoFactorAuthAsAdmin([
            'user_id' => $user->id,
        ]);

        Event::assertDispatched(UserTwoFactorDisabled::class, function (UserTwoFactorDisabled $event) use ($user) {
            return $event->user->is($user);
        });
    }

    public function test_lost_two_factor_access_dispatches_disabled_event(): void
    {
        $user = User::factory()->create([
            'tfa_enabled' => true,
            'tfa_secret' => 'secret',
        ]);

        $email = Email::query()->create([
            'user_id' => $user->id,
            'token' => 'disable-tfa-token',
            'identifier' => 'account.2fa.disable.request',
            'to' => $user->email,
            'subject' => 'Disable two-factor authentication',
            'lines' => ['Confirm disabling two-factor authentication.'],
        ]);

        Event::fake([UserTwoFactorDisabled::class]);

        app(AuthController::class)->lostAccessTwoFactor($email->token);

        Event::assertDispatched(UserTwoFactorDisabled::class, function (UserTwoFactorDisabled $event) use ($user) {
            return $event->user->is($user);
        });
    }

    public function test_bans_dispatch_events(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        Event::fake([UserBanned::class, UserBanLifted::class]);

        $ban = User::actions()->banUserAsAdmin([
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'reason' => 'Abuse',
        ]);

        Event::assertDispatched(UserBanned::class, function (UserBanned $event) use ($user, $ban) {
            return $event->user->is($user) && $event->ban->is($ban);
        });

        User::actions()->liftBanAsAdmin([
            'ban_id' => $ban->id,
            'admin_id' => $admin->id,
        ]);

        Event::assertDispatched(UserBanLifted::class, function (UserBanLifted $event) use ($user, $ban) {
            return $event->user->is($user) && $event->ban->is($ban);
        });
    }

    public function test_balance_credit_dispatches_event(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        Event::fake([UserBalanceCredited::class]);

        $user->updateBalance('+', 25, 'Balance top-up');
        $user->updateBalance('-', 5, 'Renewal');

        Event::assertDispatchedTimes(UserBalanceCredited::class, 1);
        Event::assertDispatched(UserBalanceCredited::class, function (UserBalanceCredited $event) use ($user) {
            return $event->user->is($user)
                && (float) $event->amount === 25.0
                && $event->description === 'Balance top-up';
        });
    }

    public function test_order_transfer_upgrade_and_balance_renewal_dispatch_events(): void
    {
        [$order, $packagePrice, $replacementPrice] = $this->orderWithPrices();
        $recipient = User::factory()->create();

        Event::fake([OrderTransferred::class, OrderUpgraded::class, OrderRenewed::class]);

        Order::actions()->transferOrderAsAdmin([
            'order_id' => $order->id,
            'user_id' => $recipient->id,
        ]);

        Event::assertDispatched(OrderTransferred::class, function (OrderTransferred $event) use ($order, $recipient) {
            return $event->order->is($order)
                && $event->fromUser->is($order->user)
                && $event->toUser->is($recipient);
        });

        Order::actions()->upgradeOrderAsAdmin([
            'order_id' => $order->id,
            'package_price_id' => $replacementPrice->id,
        ]);

        Event::assertDispatched(OrderUpgraded::class, function (OrderUpgraded $event) use ($order, $packagePrice, $replacementPrice) {
            return $event->order->is($order)
                && $event->previousPackagePrice->is($packagePrice)
                && $event->packagePrice->is($replacementPrice);
        });

        $order->refresh();
        $order->user->update(['balance' => 1000]);

        $order->attemptBalanceRenewal();

        Event::assertDispatched(OrderRenewed::class, function (OrderRenewed $event) use ($order) {
            return $event->order->is($order) && (int) $event->renewal_days === 30;
        });
    }

    public function test_subscription_lifecycle_dispatches_events(): void
    {
        $user = User::factory()->create();

        $subscription = Subscription::query()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'description' => 'Hosting',
            'amount' => 10,
            'frequency' => 30,
            'currency' => 'USD',
        ]);

        Event::fake([
            SubscriptionActivated::class,
            SubscriptionCancelled::class,
            SubscriptionDeactivated::class,
        ]);

        $subscription->activated('sub_123', now()->addDays(30));

        Event::assertDispatched(SubscriptionActivated::class, function (SubscriptionActivated $event) use ($subscription) {
            return $event->subscription->is($subscription);
        });

        $subscription->cancelled('Customer request');

        Event::assertDispatched(SubscriptionCancelled::class, function (SubscriptionCancelled $event) use ($subscription) {
            return $event->subscription->is($subscription) && $event->reason === 'Customer request';
        });

        $active = Subscription::query()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'description' => 'Hosting',
            'amount' => 10,
            'frequency' => 30,
            'currency' => 'USD',
        ]);

        $active->inactive();

        Event::assertDispatched(SubscriptionDeactivated::class, function (SubscriptionDeactivated $event) use ($active) {
            return $event->subscription->is($active);
        });
    }

    /**
     * @return array{0: Order, 1: PackagePrice, 2: PackagePrice}
     */
    private function orderWithPrices(): array
    {
        Extension::query()->updateOrCreate(
            ['identifier' => 'server-universal'],
            [
                'namespace' => Server::class,
                'type' => 'server',
                'name' => 'Universal',
                'status' => 'enabled',
                'version' => '1.0.0',
            ]
        );

        $connection = ServerConnection::query()->create([
            'alias' => 'events-connection-'.uniqid(),
            'extension_identifier' => 'server-universal',
            'status' => 'healthy',
            'is_active' => true,
            'prevent_purchasing' => false,
            'receive_alerts' => false,
            'config' => [],
        ]);

        $category = Category::query()->create([
            'name' => 'Hosting',
            'slug' => 'hosting-'.uniqid(),
            'icon' => 'server',
            'status' => 'active',
        ]);

        $package = Package::query()->create([
            'category_id' => $category->id,
            'connection_id' => $connection->id,
            'slug' => 'starter-'.uniqid(),
            'name' => 'Starter '.uniqid(),
            'status' => 'active',
        ]);

        $packagePrice = PackagePrice::query()->create([
            'package_id' => $package->id,
            'period_in_days' => 30,
            'price' => 10,
        ]);

        $replacementPrice = PackagePrice::query()->create([
            'package_id' => $package->id,
            'period_in_days' => 30,
            'price' => 20,
        ]);

        $order = Order::query()->create([
            'user_id' => User::factory()->create()->id,
            'package_id' => $package->id,
            'package_price_id' => $packagePrice->id,
            'status' => 'active',
            'cycle_price' => 1,
            'period_in_days' => 30,
        ]);

        return [$order, $packagePrice, $replacementPrice];
    }
}
