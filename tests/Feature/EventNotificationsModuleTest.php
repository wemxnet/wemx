<?php

namespace Tests\Feature;

use App\Events\Users\UserLoggedIn;
use App\Events\Users\UserLoginFailed;
use App\Models\Extension;
use App\Models\User;
use Extensions\Modules\EventNotifications\EventCatalog;
use Extensions\Modules\EventNotifications\Listeners\NotifyEventSubscribers;
use Extensions\Modules\EventNotifications\Mail\EventNotificationMail;
use Extensions\Modules\EventNotifications\Models\EventNotificationSubscription;
use Extensions\Modules\EventNotifications\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EventNotificationsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => 'extensions/Modules/EventNotifications/Migrations',
            '--force' => true,
        ]);

        Event::subscribe(NotifyEventSubscribers::class);
        Volt::mount(base_path('extensions/Modules/EventNotifications/Views'));
    }

    public function test_module_registers_an_admin_sidebar_item(): void
    {
        $elements = collect((new Module)->elements());

        $this->assertTrue($elements->contains(
            fn (array $element): bool => ($element['element'] ?? null) === 'admin-sidebar-item'
                && ($element['permission'] ?? null) === 'admin.event-notifications'
                && ($element['attributes']['href'] ?? null) === '/admin/event-notifications'
        ));
    }

    public function test_catalog_includes_application_events(): void
    {
        $this->assertContains(UserLoggedIn::class, EventCatalog::classes());
        $this->assertSame('User Logged In', EventCatalog::label(UserLoggedIn::class));
    }

    public function test_discord_subscription_is_notified_for_selected_events_only(): void
    {
        $user = User::factory()->create();
        $webhook = 'https://discord.com/api/webhooks/123/abcdef';

        EventNotificationSubscription::actions()->save([
            'name' => 'Discord alerts',
            'channel' => 'discord',
            'destination' => $webhook,
            'events' => [UserLoggedIn::class],
            'is_active' => true,
        ]);

        Http::fake([
            'discord.com/*' => Http::response('', 204),
        ]);

        UserLoginFailed::dispatch($user->email);
        UserLoggedIn::dispatch($user);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($webhook, $user): bool {
            return $request->url() === $webhook
                && $request['embeds'][0]['title'] === 'User Logged In'
                && str_contains($request['embeds'][0]['fields'][0]['value'], $user->email);
        });
    }

    public function test_email_subscription_sends_the_event_summary(): void
    {
        $user = User::factory()->create();

        EventNotificationSubscription::actions()->save([
            'name' => 'Email alerts',
            'channel' => 'email',
            'destination' => 'ops@example.com',
            'events' => [UserLoggedIn::class],
            'is_active' => true,
        ]);

        Mail::fake();

        UserLoggedIn::dispatch($user);

        Mail::assertSent(EventNotificationMail::class, function (EventNotificationMail $mail): bool {
            return $mail->hasTo('ops@example.com') && $mail->title === 'User Logged In';
        });
    }

    public function test_paused_subscription_is_not_notified(): void
    {
        $user = User::factory()->create();

        EventNotificationSubscription::actions()->save([
            'name' => 'Paused',
            'channel' => 'discord',
            'destination' => 'https://discord.com/api/webhooks/123/abcdef',
            'events' => [UserLoggedIn::class],
            'is_active' => false,
        ]);

        Http::fake();

        UserLoggedIn::dispatch($user);

        Http::assertNothingSent();
    }

    public function test_discord_destination_must_be_a_webhook_url(): void
    {
        try {
            EventNotificationSubscription::actions()->save([
                'name' => 'Bad webhook',
                'channel' => 'discord',
                'destination' => 'https://example.com/hook',
                'events' => [UserLoggedIn::class],
                'is_active' => true,
            ]);

            $this->fail('Expected a validation exception for a non-Discord webhook.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('destination', $exception->errors());
        }
    }

    public function test_admin_can_save_a_subscription_from_the_page(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin);

        Volt::test('event-notifications.subscriptions')
            ->set('name', 'Billing')
            ->set('channel', 'email')
            ->set('destination', 'billing@example.com')
            ->set('selectedEvents', [UserLoggedIn::class])
            ->set('isActive', true)
            ->call('save')
            ->assertHasNoErrors();

        $subscription = EventNotificationSubscription::query()->first();

        $this->assertNotNull($subscription);
        $this->assertSame('Billing', $subscription->name);
        $this->assertSame('billing@example.com', $subscription->destination);
        $this->assertSame([UserLoggedIn::class], $subscription->events);
    }

    public function test_enabling_the_module_registers_its_sidebar_element(): void
    {
        Extension::discover();

        $extension = Extension::query()->find('module-event-notifications');

        $this->assertNotNull($extension);

        $extension->enable();

        $this->assertDatabaseHas('extension_elements', [
            'extension_identifier' => 'module-event-notifications',
            'element' => 'admin-sidebar-item',
            'permission' => 'admin.event-notifications',
        ]);
    }
}
