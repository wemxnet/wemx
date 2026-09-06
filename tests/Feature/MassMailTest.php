<?php

namespace Tests\Feature;

use App\Jobs\DeliverCustomerMail;
use App\Models\Category;
use App\Models\Email;
use App\Models\MassMail;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Role;
use App\Models\ServerConnection;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MassMailTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        config([
            'app.installed' => true,
            'app.license_key' => 'WMX-TESTING-KEY',
        ]);

        Cache::put('lcs_checked_at', now(), 21600);

        $this->admin = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_admin_can_open_the_mass_mailer(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.emails.mass-mails.index'))
            ->assertOk()
            ->assertSee(__('messages.mass_mails'));

        $this->get(route('admin.emails.mass-mails.create'))
            ->assertOk()
            ->assertSeeLivewire('admin_area.default.emails.livewire.mass-mail-form');
    }

    public function test_customers_cannot_open_the_mass_mailer(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->get(route('admin.emails.mass-mails.index'))
            ->assertForbidden();
    }

    public function test_staff_without_permission_cannot_open_the_mass_mailer(): void
    {
        $this->actingAsStaff(['admin.emails.index']);

        $this->get(route('admin.emails.mass-mails.index'))
            ->assertForbidden();
    }

    public function test_staff_with_permission_can_open_the_mass_mailer(): void
    {
        $this->actingAsStaff(['admin.emails.mass-mails']);

        $this->get(route('admin.emails.mass-mails.index'))
            ->assertOk();
    }

    public function test_preview_does_not_create_or_send_emails(): void
    {
        $this->actingAsAdmin();
        $this->customer(['first_name' => 'Ada']);

        $html = Volt::test('admin_area.default.emails.livewire.mass-mail-form')
            ->set('subject', 'Hello {{user_name}}')
            ->set('body', 'Welcome {{user_name}}')
            ->instance()
            ->previewHtml();

        $this->assertStringContainsString('Welcome Ada', $html);
        $this->assertSame(0, Email::query()->count());
        $this->assertSame(0, MassMail::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_admin_can_queue_a_mass_mail_to_all_customers(): void
    {
        $this->actingAsAdmin();
        $first = $this->customer();
        $second = $this->customer();

        Volt::test('admin_area.default.emails.livewire.mass-mail-form')
            ->set('audience_type', MassMail::AUDIENCE_ALL_CUSTOMERS)
            ->set('subject', 'Platform update')
            ->set('body', "Hello {{user_name}},\n\nWe have news.")
            ->set('button_text', 'Open dashboard')
            ->set('button_url', 'https://example.com/dashboard')
            ->call('queue')
            ->assertHasNoErrors()
            ->assertRedirect();

        $massMail = MassMail::query()->first();

        $this->assertNotNull($massMail);
        $this->assertSame('Platform update', $massMail->subject);
        $this->assertSame(MassMail::STATUS_QUEUED, $massMail->status);
        $this->assertSame(2, $massMail->recipient_count);
        $this->assertSame($this->admin->id, $massMail->created_by);

        $this->assertSame(0, Email::query()->count());
        Queue::assertNothingPushed();

        $this->assertTrue($massMail->audienceQuery()->whereKey([$first->id, $second->id])->count() === 2);
    }

    public function test_subject_and_body_are_required(): void
    {
        $this->actingAsAdmin();
        $this->customer();

        Volt::test('admin_area.default.emails.livewire.mass-mail-form')
            ->set('subject', '')
            ->set('body', '')
            ->call('queue')
            ->assertHasErrors(['subject', 'body']);

        $this->assertSame(0, MassMail::query()->count());
    }

    public function test_empty_audience_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->expectException(ValidationException::class);

        MassMail::actions()->queueAsAdmin([
            'created_by' => $this->admin->id,
            'subject' => 'Nobody here',
            'body' => 'This should not queue.',
            'audience_type' => MassMail::AUDIENCE_ALL_CUSTOMERS,
        ]);
    }

    public function test_staff_are_excluded_from_the_audience(): void
    {
        $this->actingAsAdmin();
        $customer = $this->customer();
        $this->staffUser(['admin.users.update']);

        $ids = MassMail::customersQuery(MassMail::AUDIENCE_ALL_CUSTOMERS)->pluck('id');

        $this->assertTrue($ids->contains($customer->id));
        $this->assertFalse($ids->contains($this->admin->id));
        $this->assertSame(1, $ids->count());
    }

    public function test_audience_can_target_customers_with_a_package_and_status(): void
    {
        $this->actingAsAdmin();

        $matching = $this->customer();
        $wrongStatus = $this->customer();
        $otherPackage = $this->customer();
        $noOrder = $this->customer();

        $starter = $this->createPackage('Starter');
        $pro = $this->createPackage('Pro');

        $this->createOrder($matching, $starter, 'active');
        $this->createOrder($wrongStatus, $starter, 'suspended');
        $this->createOrder($otherPackage, $pro, 'active');

        $ids = MassMail::customersQuery(MassMail::AUDIENCE_WITH_PACKAGE, [
            'package_id' => $starter->id,
            'order_status' => 'active',
        ])->pluck('id');

        $this->assertTrue($ids->contains($matching->id));
        $this->assertFalse($ids->contains($wrongStatus->id));
        $this->assertFalse($ids->contains($otherPackage->id));
        $this->assertFalse($ids->contains($noOrder->id));
    }

    public function test_audience_can_target_customers_with_orders(): void
    {
        $this->actingAsAdmin();

        $withOrder = $this->customer();
        $withoutOrder = $this->customer();
        $package = $this->createPackage('Starter');
        $this->createOrder($withOrder, $package, 'pending');

        $withOrderIds = MassMail::customersQuery(MassMail::AUDIENCE_WITH_ORDERS)->pluck('id');
        $withoutOrderIds = MassMail::customersQuery(MassMail::AUDIENCE_WITHOUT_ORDERS)->pluck('id');

        $this->assertTrue($withOrderIds->contains($withOrder->id));
        $this->assertFalse($withOrderIds->contains($withoutOrder->id));
        $this->assertTrue($withoutOrderIds->contains($withoutOrder->id));
        $this->assertFalse($withoutOrderIds->contains($withOrder->id));
    }

    public function test_audience_can_target_unpaid_invoices_and_subscribers(): void
    {
        $this->actingAsAdmin();

        $unpaid = $this->customer(['is_subscribed' => false]);
        $subscribed = $this->customer(['is_subscribed' => true]);

        Payment::query()->create([
            'user_id' => $unpaid->id,
            'status' => 'unpaid',
            'description' => 'Open invoice',
            'total' => 10,
        ]);

        $this->assertTrue(
            MassMail::customersQuery(MassMail::AUDIENCE_UNPAID_INVOICES)->pluck('id')->contains($unpaid->id)
        );
        $this->assertFalse(
            MassMail::customersQuery(MassMail::AUDIENCE_UNPAID_INVOICES)->pluck('id')->contains($subscribed->id)
        );
        $this->assertTrue(
            MassMail::customersQuery(MassMail::AUDIENCE_SUBSCRIBED)->pluck('id')->contains($subscribed->id)
        );
        $this->assertFalse(
            MassMail::customersQuery(MassMail::AUDIENCE_SUBSCRIBED)->pluck('id')->contains($unpaid->id)
        );
    }

    public function test_audience_can_target_gateway_subscriptions_and_country(): void
    {
        $this->actingAsAdmin();

        $gb = $this->customer(['country' => 'GB']);
        $us = $this->customer(['country' => 'US']);

        Subscription::query()->create([
            'user_id' => $gb->id,
            'subscription_id' => 'sub_test',
            'status' => 'active',
            'description' => 'Starter',
            'currency' => 'USD',
            'amount' => 10,
            'frequency' => 30,
        ]);

        $this->assertTrue(
            MassMail::customersQuery(MassMail::AUDIENCE_WITH_SUBSCRIPTION)->pluck('id')->contains($gb->id)
        );
        $this->assertFalse(
            MassMail::customersQuery(MassMail::AUDIENCE_WITH_SUBSCRIPTION)->pluck('id')->contains($us->id)
        );
        $this->assertTrue(
            MassMail::customersQuery(MassMail::AUDIENCE_BY_COUNTRY, ['country' => 'US'])->pluck('id')->contains($us->id)
        );
        $this->assertFalse(
            MassMail::customersQuery(MassMail::AUDIENCE_BY_COUNTRY, ['country' => 'US'])->pluck('id')->contains($gb->id)
        );
    }

    public function test_scheduler_sends_queued_mass_mails_in_the_background(): void
    {
        $this->actingAsAdmin();
        $first = $this->customer(['first_name' => 'Ada', 'username' => 'ada']);
        $second = $this->customer(['first_name' => 'Grace', 'username' => 'grace']);

        $massMail = MassMail::actions()->queueAsAdmin([
            'created_by' => $this->admin->id,
            'subject' => 'Hello {{user_name}}',
            'body' => 'Hi {{user_username}} from {{app_name}}.',
            'audience_type' => MassMail::AUDIENCE_ALL_CUSTOMERS,
        ]);

        Artisan::call('cronjobs:mass-mails:send');

        $massMail->refresh();

        $this->assertSame(MassMail::STATUS_SENT, $massMail->status);
        $this->assertSame(2, $massMail->sent_count);
        $this->assertNotNull($massMail->completed_at);

        $emails = Email::query()->orderBy('user_id')->get();
        $this->assertCount(2, $emails);
        $this->assertSame('admin.mass-email', $emails[0]->identifier);
        $this->assertSame($massMail->id, $emails[0]->mailable_id);
        $this->assertSame(MassMail::class, $emails[0]->mailable_type);

        $adaEmail = $emails->firstWhere('user_id', $first->id);
        $graceEmail = $emails->firstWhere('user_id', $second->id);

        $this->assertSame('Hello Ada', $adaEmail->subject);
        $this->assertSame(['Hi ada from '.settings('app_name', 'My Application').'.'], $adaEmail->lines);
        $this->assertSame('Hello Grace', $graceEmail->subject);

        Queue::assertPushed(DeliverCustomerMail::class, 2);
    }

    public function test_scheduled_mass_mails_wait_until_their_send_time(): void
    {
        $this->actingAsAdmin();
        $this->customer();

        $massMail = MassMail::actions()->queueAsAdmin([
            'created_by' => $this->admin->id,
            'subject' => 'Later',
            'body' => 'Send this later.',
            'audience_type' => MassMail::AUDIENCE_ALL_CUSTOMERS,
            'scheduled_at' => now()->addHour(),
        ]);

        Artisan::call('cronjobs:mass-mails:send');

        $massMail->refresh();
        $this->assertSame(MassMail::STATUS_QUEUED, $massMail->status);
        $this->assertSame(0, Email::query()->count());

        $this->travel(61)->minutes();

        Artisan::call('cronjobs:mass-mails:send');

        $massMail->refresh();
        $this->assertSame(MassMail::STATUS_SENT, $massMail->status);
        $this->assertSame(1, Email::query()->count());
    }

    public function test_cancelled_mass_mails_are_not_sent(): void
    {
        $this->actingAsAdmin();
        $this->customer();

        $massMail = MassMail::actions()->queueAsAdmin([
            'created_by' => $this->admin->id,
            'subject' => 'Cancel me',
            'body' => 'This should not go out.',
            'audience_type' => MassMail::AUDIENCE_ALL_CUSTOMERS,
        ]);

        Volt::test('admin_area.default.emails.livewire.mass-mail-show', ['massMail' => $massMail])
            ->call('cancel')
            ->assertHasNoErrors();

        $massMail->refresh();
        $this->assertSame(MassMail::STATUS_CANCELLED, $massMail->status);

        Artisan::call('cronjobs:mass-mails:send');

        $this->assertSame(0, Email::query()->count());
    }

    public function test_user_without_permission_cannot_queue_from_the_form(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer);

        Volt::test('admin_area.default.emails.livewire.mass-mail-form')
            ->set('subject', 'Hello')
            ->set('body', 'This should not send.')
            ->call('queue')
            ->assertForbidden();

        $this->assertSame(0, MassMail::query()->count());
    }

    public function test_package_is_required_for_package_audience(): void
    {
        $this->actingAsAdmin();
        $this->customer();

        Volt::test('admin_area.default.emails.livewire.mass-mail-form')
            ->set('audience_type', MassMail::AUDIENCE_WITH_PACKAGE)
            ->set('subject', 'Package notice')
            ->set('body', 'Please read this.')
            ->call('queue')
            ->assertHasErrors(['package_id']);
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin)
            ->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()]);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function actingAsStaff(array $permissions): self
    {
        $staff = $this->staffUser($permissions);

        return $this->actingAs($staff)
            ->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()]);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function staffUser(array $permissions): User
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

        return $staff->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function customer(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'status' => 'active',
            'email_verified_at' => now(),
        ], $attributes));
    }

    protected function createPackage(string $name): Package
    {
        $connection = ServerConnection::query()->create([
            'extension_identifier' => 'pterodactyl',
            'alias' => $name.' panel',
            'status' => 'online',
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'status' => 'active',
            'name' => $name.' category',
            'slug' => str($name)->slug()->append('-'.str()->random(6)),
            'icon' => 'default.png',
        ]);

        return Package::query()->create([
            'category_id' => $category->id,
            'connection_id' => $connection->id,
            'slug' => str($name)->slug()->append('-'.str()->random(6)),
            'name' => $name.' '.str()->random(4),
            'icon' => 'default.png',
            'status' => 'active',
        ]);
    }

    protected function createOrder(User $user, Package $package, string $status): Order
    {
        return Order::query()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => $status,
            'cycle_price' => 10,
            'period_in_days' => 30,
            'due_date' => now()->addDays(10),
        ]);
    }
}
