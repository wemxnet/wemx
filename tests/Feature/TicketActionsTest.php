<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Email;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use Extensions\Modules\Tickets\Models\Ticket;
use Extensions\Modules\Tickets\Models\TicketDepartment;
use Extensions\Modules\Tickets\Models\TicketMember;
use Extensions\Modules\Tickets\Models\TicketMessage;
use Extensions\Modules\Tickets\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketActionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected TicketDepartment $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => 'extensions/Modules/Tickets/Migrations',
        ]);

        if (! Route::has('tickets.view')) {
            require base_path('extensions/Modules/Tickets/routes.php');
        }

        Mail::fake();

        $this->admin = User::factory()->create();
        $this->customer = User::factory()->create();
        $this->department = TicketDepartment::query()->where('slug', 'general')->firstOrFail();
    }

    public function test_client_can_create_a_ticket_with_markdown_message(): void
    {
        $ticket = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'Cannot access my server',
            'body' => 'SSH fails with **permission denied**.',
            'priority' => Ticket::PRIORITY_HIGH,
        ]);

        $this->assertSame(1, $ticket->number);
        $this->assertSame('Cannot access my server', $ticket->title);
        $this->assertSame(Ticket::PRIORITY_HIGH, $ticket->priority);
        $this->assertTrue($ticket->isOpen());
        $this->assertSame(Ticket::REPLY_CLIENT, $ticket->last_reply_from);
        $this->assertNotNull($ticket->token);

        $this->assertDatabaseHas('ticket_members', [
            'ticket_id' => $ticket->id,
            'user_id' => $this->customer->id,
            'role' => TicketMember::ROLE_OWNER,
            'is_subscribed' => true,
        ]);

        $comments = $ticket->messages()->where('type', TicketMessage::TYPE_COMMENT)->get();
        $this->assertCount(2, $comments);
        $this->assertStringContainsString('permission denied', $comments->first()->body);
        $this->assertNotNull($this->department->auto_response);
        $this->assertSame($this->department->auto_response, $comments->last()->body);
        $this->assertTrue($comments->last()->is_staff);
        $this->assertTrue($comments->last()->from_admin);
        $this->assertFalse($comments->first()->from_admin);
    }

    public function test_guest_cannot_create_ticket_when_department_disallows_it(): void
    {
        $this->expectException(ValidationException::class);

        Ticket::actions()->createAsGuest([
            'department_id' => $this->department->id,
            'title' => 'Help',
            'body' => 'I need help',
            'priority' => Ticket::PRIORITY_MEDIUM,
            'guest_name' => 'Alex',
            'guest_email' => 'alex@example.com',
        ]);
    }

    public function test_guest_can_create_ticket_when_department_allows_it(): void
    {
        $this->department->update(['allow_guest_tickets' => true]);

        $ticket = Ticket::actions()->createAsGuest([
            'department_id' => $this->department->id,
            'title' => 'Billing question',
            'body' => 'How do I change my card?',
            'priority' => Ticket::PRIORITY_LOW,
            'guest_name' => 'Alex Guest',
            'guest_email' => 'alex@example.com',
        ]);

        $this->assertNull($ticket->user_id);
        $this->assertSame('Alex Guest', $ticket->guest_name);
        $this->assertSame('alex@example.com', $ticket->guest_email);
        $this->assertTrue($ticket->canBeViewedBy(null, $ticket->token));

        $email = Email::query()->where('to', 'alex@example.com')->where('mailable_id', $ticket->id)->first();
        $this->assertNotNull($email);
        $this->assertTrue($email->display);
        $this->assertStringContainsString('/tickets/guest/'.$ticket->token, $email->button_url);
        $this->assertStringContainsString('member=', $email->button_url);
    }

    public function test_staff_inbox_lists_unanswered_high_priority_tickets_first(): void
    {
        $lowAnswered = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'Low answered',
            'body' => 'Hello',
            'priority' => Ticket::PRIORITY_LOW,
        ]);

        Ticket::actions()->replyAsAdmin([
            'ticket_id' => $lowAnswered->id,
            'admin_user_id' => $this->admin->id,
            'body' => 'We are looking into this.',
        ]);

        $urgentWaiting = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'Urgent waiting',
            'body' => 'Server is down',
            'priority' => Ticket::PRIORITY_URGENT,
        ]);

        $ordered = Ticket::query()->orderedForStaff()->pluck('id')->all();

        $this->assertSame($urgentWaiting->id, $ordered[0]);
        $this->assertContains($lowAnswered->id, $ordered);
        $this->assertTrue(array_search($urgentWaiting->id, $ordered) < array_search($lowAnswered->id, $ordered));
    }

    public function test_staff_overview_queue_excludes_answered_tickets_and_orders_by_importance(): void
    {
        $answered = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'Already answered',
            'body' => 'Thanks',
            'priority' => Ticket::PRIORITY_URGENT,
        ]);

        Ticket::actions()->replyAsAdmin([
            'ticket_id' => $answered->id,
            'admin_user_id' => $this->admin->id,
            'body' => 'Resolved on our side.',
        ]);

        $lowWaiting = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'Low waiting',
            'body' => 'Question',
            'priority' => Ticket::PRIORITY_LOW,
        ]);

        $urgentWaiting = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'Urgent waiting',
            'body' => 'Outage',
            'priority' => Ticket::PRIORITY_URGENT,
        ]);

        $queue = Ticket::needingStaffReply();

        $this->assertSame([$urgentWaiting->id, $lowWaiting->id], $queue->pluck('id')->all());
        $this->assertFalse($queue->contains('id', $answered->id));
    }

    public function test_user_ticket_list_only_includes_tickets_opened_by_that_user(): void
    {
        $other = User::factory()->create();

        $own = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'My outage',
            'body' => 'Cannot connect',
            'priority' => Ticket::PRIORITY_HIGH,
        ]);

        Ticket::actions()->createAsClient([
            'user_id' => $other->id,
            'department_id' => $this->department->id,
            'title' => 'Someone else',
            'body' => 'Different account',
            'priority' => Ticket::PRIORITY_URGENT,
        ]);

        $tickets = Ticket::openedByUser($this->customer);

        $this->assertTrue($tickets->contains('id', $own->id));
        $this->assertCount(1, $tickets);
        $this->assertSame('My outage', $tickets->first()->title);
    }

    public function test_module_registers_dashboard_and_user_ticket_elements(): void
    {
        $elements = collect((new Module)->elements());

        $this->assertTrue($elements->contains(
            fn (array $element): bool => ($element['element'] ?? null) === 'admin-dashboard-main-view'
                && ($element['view'] ?? null) === 'tickets::admin_area.default.dashboard.widgets.needs-reply'
        ));
        $this->assertTrue($elements->contains(
            fn (array $element): bool => ($element['element'] ?? null) === 'admin-customer-bottom-view'
                && ($element['view'] ?? null) === 'tickets::admin_area.default.users.widgets.user-tickets'
        ));
    }

    public function test_staff_label_requires_the_message_to_be_sent_from_admin_area(): void
    {
        $ticket = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'Need help',
            'body' => 'Initial message',
            'priority' => Ticket::PRIORITY_MEDIUM,
        ]);

        $adminReply = Ticket::actions()->replyAsAdmin([
            'ticket_id' => $ticket->id,
            'admin_user_id' => $this->admin->id,
            'body' => 'We restarted the node.',
        ]);

        $this->assertTrue($adminReply->from_admin);
        $this->assertTrue($adminReply->is_staff);
        $this->assertSame(Ticket::REPLY_STAFF, $ticket->fresh()->last_reply_from);

        $staffFromClient = Ticket::actions()->replyAsParticipant([
            'ticket_id' => $ticket->id,
            'user_id' => $this->admin->id,
            'body' => 'Adding more logs from my account.',
        ]);

        $this->assertFalse($staffFromClient->from_admin);
        $this->assertTrue($staffFromClient->is_staff);
        $this->assertSame(Ticket::REPLY_CLIENT, $ticket->fresh()->last_reply_from);

        $note = Ticket::actions()->addInternalNote([
            'ticket_id' => $ticket->id,
            'admin_user_id' => $this->admin->id,
            'body' => 'Check the hypervisor logs.',
        ]);

        $this->assertTrue($note->from_admin);
        $this->assertTrue($note->isNote());
    }

    public function test_client_can_reply_subscribe_and_close_a_ticket(): void
    {
        $ticket = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'Need help',
            'body' => 'Initial message',
            'priority' => Ticket::PRIORITY_MEDIUM,
        ]);

        $reply = Ticket::actions()->replyAsParticipant([
            'ticket_id' => $ticket->id,
            'user_id' => $this->customer->id,
            'body' => 'More details here',
        ]);

        $this->assertSame('More details here', $reply->body);
        $this->assertFalse($reply->from_admin);
        $this->assertSame(Ticket::REPLY_CLIENT, $ticket->fresh()->last_reply_from);

        $member = Ticket::actions()->unsubscribe([
            'ticket_id' => $ticket->id,
            'user_id' => $this->customer->id,
        ]);
        $this->assertFalse($member->is_subscribed);

        $member = Ticket::actions()->subscribe([
            'ticket_id' => $ticket->id,
            'user_id' => $this->customer->id,
        ]);
        $this->assertTrue($member->is_subscribed);

        Ticket::actions()->close([
            'ticket_id' => $ticket->id,
            'user_id' => $this->customer->id,
        ]);

        $this->assertTrue($ticket->fresh()->isClosed());
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'event_type' => 'status_changed',
        ]);
    }

    public function test_invite_requires_department_setting_for_guests(): void
    {
        $this->department->update(['allow_invites' => true, 'allow_guest_members' => false]);

        $ticket = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'Need help',
            'body' => 'Initial message',
            'priority' => Ticket::PRIORITY_MEDIUM,
        ]);

        try {
            Ticket::actions()->invite([
                'ticket_id' => $ticket->id,
                'user_id' => $this->customer->id,
                'email' => 'someone-new@example.com',
            ]);
            $this->fail('Expected validation exception');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->department->update(['allow_guest_members' => true]);

        $member = Ticket::actions()->invite([
            'ticket_id' => $ticket->id,
            'user_id' => $this->customer->id,
            'email' => 'someone-new@example.com',
        ]);

        $this->assertSame('someone-new@example.com', $member->email);
        $this->assertNull($member->user_id);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'event_type' => 'member_added',
        ]);
    }

    public function test_admin_can_manage_departments_and_change_ticket_priority(): void
    {
        $department = TicketDepartment::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'name' => 'Escalations',
            'allow_guest_tickets' => true,
            'allow_invites' => false,
            'auto_response' => 'Escalations will reply soon.',
        ]);

        $this->assertSame('escalations', $department->slug);
        $this->assertTrue($department->allow_guest_tickets);
        $this->assertFalse($department->allow_invites);
        $this->assertSame(0, $department->auto_close_days);

        TicketDepartment::actions()->updateAsAdmin([
            'admin_user_id' => $this->admin->id,
            'department_id' => $department->id,
            'auto_close_days' => 14,
        ]);

        $this->assertSame(14, $department->fresh()->auto_close_days);

        $ticket = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $department->id,
            'title' => 'Invoice issue',
            'body' => 'Wrong amount',
            'priority' => Ticket::PRIORITY_MEDIUM,
        ]);

        Ticket::actions()->changePriority([
            'ticket_id' => $ticket->id,
            'admin_user_id' => $this->admin->id,
            'priority' => Ticket::PRIORITY_URGENT,
        ]);

        $this->assertSame(Ticket::PRIORITY_URGENT, $ticket->fresh()->priority);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'event_type' => 'priority_changed',
        ]);
    }

    public function test_client_cannot_reply_to_a_closed_ticket(): void
    {
        $ticket = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'Need help',
            'body' => 'Initial message',
            'priority' => Ticket::PRIORITY_MEDIUM,
        ]);

        Ticket::actions()->close([
            'ticket_id' => $ticket->id,
            'user_id' => $this->customer->id,
        ]);

        $this->expectException(ValidationException::class);

        Ticket::actions()->replyAsParticipant([
            'ticket_id' => $ticket->id,
            'user_id' => $this->customer->id,
            'body' => 'Still broken',
        ]);
    }

    public function test_admin_can_lock_a_ticket_and_clients_cannot_reply_until_unlocked(): void
    {
        $ticket = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'Need help',
            'body' => 'Initial message',
            'priority' => Ticket::PRIORITY_MEDIUM,
        ]);

        $locked = Ticket::actions()->lock([
            'ticket_id' => $ticket->id,
            'admin_user_id' => $this->admin->id,
        ]);

        $this->assertTrue($locked->isLocked());
        $this->assertSame($this->admin->id, $locked->locked_by);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'event_type' => 'lock_changed',
        ]);

        try {
            Ticket::actions()->replyAsParticipant([
                'ticket_id' => $ticket->id,
                'user_id' => $this->customer->id,
                'body' => 'Can I still reply?',
            ]);
            $this->fail('Expected validation exception');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticket_id', $exception->errors());
        }

        try {
            Ticket::actions()->close([
                'ticket_id' => $ticket->id,
                'user_id' => $this->customer->id,
            ]);
            $this->fail('Expected validation exception');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticket_id', $exception->errors());
        }

        $adminReply = Ticket::actions()->replyAsAdmin([
            'ticket_id' => $ticket->id,
            'admin_user_id' => $this->admin->id,
            'body' => 'Staff can still reply.',
        ]);

        $this->assertTrue($adminReply->from_admin);

        try {
            Ticket::actions()->lock([
                'ticket_id' => $ticket->id,
                'admin_user_id' => $this->customer->id,
            ]);
            $this->fail('Expected validation exception');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('admin_user_id', $exception->errors());
        }

        Ticket::actions()->unlock([
            'ticket_id' => $ticket->id,
            'admin_user_id' => $this->admin->id,
        ]);

        $this->assertFalse($ticket->fresh()->isLocked());

        $reply = Ticket::actions()->replyAsParticipant([
            'ticket_id' => $ticket->id,
            'user_id' => $this->customer->id,
            'body' => 'Thanks, unlocked now.',
        ]);

        $this->assertSame('Thanks, unlocked now.', $reply->body);
        $this->assertFalse($reply->from_admin);
    }

    public function test_inactive_departments_reject_new_tickets_but_allow_replies(): void
    {
        $this->department->update([
            'is_active' => false,
            'allow_guest_tickets' => true,
        ]);

        try {
            Ticket::actions()->createAsClient([
                'user_id' => $this->customer->id,
                'department_id' => $this->department->id,
                'title' => 'Should not open',
                'body' => 'Department is closed',
                'priority' => Ticket::PRIORITY_MEDIUM,
            ]);
            $this->fail('Expected validation exception');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('department_id', $exception->errors());
        }

        try {
            Ticket::actions()->createAsGuest([
                'department_id' => $this->department->id,
                'title' => 'Guest should not open',
                'body' => 'Department is closed',
                'priority' => Ticket::PRIORITY_MEDIUM,
                'guest_name' => 'Alex',
                'guest_email' => 'alex@example.com',
            ]);
            $this->fail('Expected validation exception');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('department_id', $exception->errors());
        }

        try {
            Ticket::actions()->createAsAdmin([
                'admin_user_id' => $this->admin->id,
                'user_id' => $this->customer->id,
                'department_id' => $this->department->id,
                'title' => 'Staff should not open',
                'body' => 'Department is closed',
                'priority' => Ticket::PRIORITY_MEDIUM,
            ]);
            $this->fail('Expected validation exception');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('department_id', $exception->errors());
        }

        TicketDepartment::actions()->updateAsAdmin([
            'admin_user_id' => $this->admin->id,
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);

        $ticket = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'Opened while active',
            'body' => 'Please help',
            'priority' => Ticket::PRIORITY_MEDIUM,
        ]);

        TicketDepartment::actions()->updateAsAdmin([
            'admin_user_id' => $this->admin->id,
            'department_id' => $this->department->id,
            'is_active' => false,
        ]);

        $this->assertFalse($this->department->fresh()->is_active);

        $reply = Ticket::actions()->replyAsParticipant([
            'ticket_id' => $ticket->id,
            'user_id' => $this->customer->id,
            'body' => 'Still need help on this ticket.',
        ]);

        $this->assertSame('Still need help on this ticket.', $reply->body);
        $this->assertTrue($ticket->fresh()->isOpen());
    }

    public function test_inactive_tickets_waiting_on_the_customer_are_auto_closed(): void
    {
        $this->department->update(['auto_close_days' => 7]);

        $ticket = $this->ticketWaitingOnCustomer();
        $ticket->update(['last_replied_at' => now()->subDays(8)]);

        $closed = Ticket::actions()->autoCloseInactive();

        $this->assertSame(1, $closed);
        $this->assertTrue($ticket->fresh()->isClosed());

        $event = $ticket->messages()->where('event_type', 'status_changed')->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertSame('inactivity', $event->meta['reason']);
        $this->assertSame(7, $event->meta['days']);
        $this->assertStringContainsString('automatically closed', $event->eventSummary());
    }

    public function test_auto_close_skips_tickets_waiting_on_staff_locked_or_disabled(): void
    {
        $this->department->update(['auto_close_days' => 7]);

        $waitingOnStaff = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'Still waiting on staff',
            'body' => 'Initial message',
            'priority' => Ticket::PRIORITY_MEDIUM,
        ]);
        $waitingOnStaff->update(['last_replied_at' => now()->subDays(8)]);

        $locked = $this->ticketWaitingOnCustomer();
        Ticket::actions()->lock([
            'ticket_id' => $locked->id,
            'admin_user_id' => $this->admin->id,
        ]);
        $locked->update(['last_replied_at' => now()->subDays(8)]);

        $disabled = TicketDepartment::actions()->createAsAdmin([
            'admin_user_id' => $this->admin->id,
            'name' => 'No Auto Close',
            'auto_close_days' => 0,
        ]);
        $disabledTicket = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $disabled->id,
            'title' => 'No auto close',
            'body' => 'Initial message',
            'priority' => Ticket::PRIORITY_MEDIUM,
        ]);
        Ticket::actions()->replyAsAdmin([
            'ticket_id' => $disabledTicket->id,
            'admin_user_id' => $this->admin->id,
            'body' => 'Please reply.',
        ]);
        $disabledTicket->update(['last_replied_at' => now()->subDays(8)]);

        $this->assertSame(0, Ticket::actions()->autoCloseInactive());
        $this->assertTrue($waitingOnStaff->fresh()->isOpen());
        $this->assertTrue($locked->fresh()->isOpen());
        $this->assertTrue($disabledTicket->fresh()->isOpen());
    }

    public function test_client_can_attach_their_order_and_admin_can_relink_it(): void
    {
        $order = $this->createOrderFor($this->customer);
        $otherOrder = $this->createOrderFor(User::factory()->create());

        $ticket = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'Server is offline',
            'body' => 'Cannot connect',
            'priority' => Ticket::PRIORITY_HIGH,
            'order_id' => $order->id,
        ]);

        $this->assertSame($order->id, $ticket->order_id);

        try {
            Ticket::actions()->createAsClient([
                'user_id' => $this->customer->id,
                'department_id' => $this->department->id,
                'title' => 'Wrong order',
                'body' => 'Should fail',
                'priority' => Ticket::PRIORITY_LOW,
                'order_id' => $otherOrder->id,
            ]);
            $this->fail('Expected validation exception');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('order_id', $exception->errors());
        }

        $linked = Ticket::actions()->linkOrder([
            'ticket_id' => $ticket->id,
            'admin_user_id' => $this->admin->id,
            'order_id' => $order->id,
        ]);
        $this->assertSame($order->id, $linked->order_id);

        try {
            Ticket::actions()->linkOrder([
                'ticket_id' => $ticket->id,
                'admin_user_id' => $this->admin->id,
                'order_id' => $otherOrder->id,
            ]);
            $this->fail('Expected validation exception');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('order_id', $exception->errors());
        }
    }

    protected function ticketWaitingOnCustomer(?TicketDepartment $department = null): Ticket
    {
        $ticket = Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => ($department ?? $this->department)->id,
            'title' => 'Need help',
            'body' => 'Initial message',
            'priority' => Ticket::PRIORITY_MEDIUM,
        ]);

        Ticket::actions()->replyAsAdmin([
            'ticket_id' => $ticket->id,
            'admin_user_id' => $this->admin->id,
            'body' => 'Please try restarting the service.',
        ]);

        return $ticket->fresh();
    }

    protected function createOrderFor(User $user): Order
    {
        $category = Category::query()->create([
            'name' => 'Hosting',
            'slug' => 'hosting-'.uniqid(),
            'icon' => 'server',
            'status' => 'active',
        ]);

        $connectionId = DB::table('server_connections')->insertGetId([
            'extension_identifier' => 'none',
            'alias' => 'conn-'.uniqid(),
            'status' => 'active',
            'is_active' => true,
            'receive_alerts' => false,
            'prevent_purchasing' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $package = Package::query()->create([
            'category_id' => $category->id,
            'connection_id' => $connectionId,
            'slug' => 'pkg-'.uniqid(),
            'name' => 'VPS '.uniqid(),
            'status' => 'active',
        ]);

        return Order::withoutEvents(fn () => Order::query()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'due_date' => now()->addMonth(),
        ]));
    }
}
