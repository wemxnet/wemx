<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\User;
use Extensions\Modules\Tickets\Commands\IngestInboundMailCommand;
use Extensions\Modules\Tickets\Models\Ticket;
use Extensions\Modules\Tickets\Models\TicketDepartment;
use Extensions\Modules\Tickets\Models\TicketMessage;
use Extensions\Modules\Tickets\Support\InboundMailParser;
use Extensions\Modules\Tickets\Support\TicketInboundMail;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TicketInboundMailTest extends TestCase
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

        if (! Route::has('tickets.view') || ! Route::has('tickets.inbound-mail')) {
            require base_path('extensions/Modules/Tickets/routes.php');
        }

        $this->app->make(Kernel::class)->registerCommand(
            $this->app->make(IngestInboundMailCommand::class)
        );

        Mail::fake();

        $this->admin = User::factory()->create();
        $this->customer = User::factory()->create();
        $this->department = TicketDepartment::query()->where('slug', 'general')->firstOrFail();
    }

    public function test_ticket_notification_emails_include_a_unique_reply_to_address(): void
    {
        $ticket = $this->openTicket();

        $email = Email::query()->where('to', $this->customer->email)->where('mailable_id', $ticket->id)->first();

        $this->assertNotNull($email);
        $this->assertSame(TicketInboundMail::replyTo($ticket), $email->data['reply_to'] ?? null);
        $this->assertNotEmpty($email->data['message_id'] ?? null);
        $this->assertStringStartsWith('ticket.'.$ticket->id.'.', $email->data['message_id']);
    }

    public function test_client_reply_from_email_is_added_to_the_timeline(): void
    {
        $ticket = $this->openTicket();

        $message = Ticket::actions()->replyFromInboundMail($this->rawReply(
            from: $this->customer->email,
            to: TicketInboundMail::replyTo($ticket),
            body: "The server is still down.\n\nOn Friday, Support wrote:\n> We restarted the node.",
        ));

        $this->assertNotNull($message);
        $this->assertSame($ticket->id, $message->ticket_id);
        $this->assertSame('The server is still down.', $message->body);
        $this->assertTrue($message->isFromEmail());
        $this->assertFalse($message->from_admin);
        $this->assertSame('email', $message->meta['source']);
        $this->assertSame(Ticket::REPLY_CLIENT, $ticket->fresh()->last_reply_from);
    }

    public function test_admin_reply_from_email_is_marked_as_staff(): void
    {
        $ticket = $this->openTicket();
        $outbound = Email::query()->where('to', $this->customer->email)->where('mailable_id', $ticket->id)->first();

        $message = Ticket::actions()->replyFromInboundMail($this->rawReply(
            from: $this->admin->email,
            to: 'staff@example.com',
            body: 'We applied the firewall rule.',
            inReplyTo: $outbound->data['message_id'],
        ));

        $this->assertNotNull($message);
        $this->assertTrue($message->from_admin);
        $this->assertTrue($message->isFromEmail());
        $this->assertSame(Ticket::REPLY_STAFF, $ticket->fresh()->last_reply_from);
    }

    public function test_unknown_senders_and_auto_replies_are_ignored(): void
    {
        $ticket = $this->openTicket();
        $replyTo = TicketInboundMail::replyTo($ticket);

        $this->assertNull(Ticket::actions()->replyFromInboundMail($this->rawReply(
            from: 'stranger@example.com',
            to: $replyTo,
            body: 'Please ignore this.',
        )));

        $this->assertNull(Ticket::actions()->replyFromInboundMail($this->rawReply(
            from: $this->customer->email,
            to: $replyTo,
            body: 'Out of office.',
            extraHeaders: ['Auto-Submitted' => 'auto-replied'],
        )));

        $this->assertSame(2, $ticket->messages()->where('type', TicketMessage::TYPE_COMMENT)->count());
    }

    public function test_duplicate_inbound_messages_are_not_imported_twice(): void
    {
        $ticket = $this->openTicket();
        $raw = $this->rawReply(
            from: $this->customer->email,
            to: TicketInboundMail::replyTo($ticket),
            body: 'Still happening.',
            extraHeaders: ['Message-ID' => '<dup-1@example.com>'],
        );

        $this->assertNotNull(Ticket::actions()->replyFromInboundMail($raw));
        $this->assertNull(Ticket::actions()->replyFromInboundMail($raw));
        $this->assertSame(1, $ticket->messages()->where('meta->source', 'email')->count());
    }

    public function test_email_reply_reopens_a_closed_ticket_but_not_a_locked_one(): void
    {
        $ticket = $this->openTicket();

        Ticket::actions()->close([
            'ticket_id' => $ticket->id,
            'user_id' => $this->customer->id,
        ]);

        $message = Ticket::actions()->replyFromInboundMail($this->rawReply(
            from: $this->customer->email,
            to: TicketInboundMail::replyTo($ticket),
            body: 'Please reopen, it broke again.',
        ));

        $this->assertNotNull($message);
        $this->assertTrue($ticket->fresh()->isOpen());
        $this->assertTrue($ticket->messages()->where('event_type', 'status_changed')->where('meta->action', 'reopened')->exists());

        Ticket::actions()->lock([
            'ticket_id' => $ticket->id,
            'admin_user_id' => $this->admin->id,
        ]);

        $this->assertNull(Ticket::actions()->replyFromInboundMail($this->rawReply(
            from: $this->customer->email,
            to: TicketInboundMail::replyTo($ticket),
            body: 'Trying again while locked.',
            extraHeaders: ['Message-ID' => '<locked-reply@example.com>'],
        )));
    }

    public function test_guest_can_reply_by_email(): void
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

        $message = Ticket::actions()->replyFromInboundMail($this->rawReply(
            from: 'alex@example.com',
            to: TicketInboundMail::replyTo($ticket),
            body: 'I found the invoices page.',
        ));

        $this->assertNotNull($message);
        $this->assertTrue($message->isFromEmail());
        $this->assertSame('alex@example.com', $message->author_email);
        $this->assertFalse($message->from_admin);
    }

    public function test_webhook_imports_a_reply_and_rejects_a_bad_token(): void
    {
        $ticket = $this->openTicket();
        $raw = $this->rawReply(
            from: $this->customer->email,
            to: TicketInboundMail::replyTo($ticket),
            body: 'Posted through the webhook.',
        );

        $this->call(
            'POST',
            '/tickets/inbound-mail?token=not-valid',
            server: ['CONTENT_TYPE' => 'message/rfc822'],
            content: $raw,
        )->assertForbidden();

        $this->call(
            'POST',
            '/tickets/inbound-mail?token='.TicketInboundMail::webhookToken(),
            server: ['CONTENT_TYPE' => 'message/rfc822'],
            content: $raw,
        )->assertNoContent();

        $this->assertTrue($ticket->messages()->where('meta->source', 'email')->exists());
    }

    public function test_artisan_command_ingests_a_raw_email_file(): void
    {
        $ticket = $this->openTicket();
        $path = tempnam(sys_get_temp_dir(), 'ticket-mail-');
        file_put_contents($path, $this->rawReply(
            from: $this->customer->email,
            to: TicketInboundMail::replyTo($ticket),
            body: 'Piped from the mail server.',
        ));

        $this->artisan('tickets:ingest-mail', ['file' => $path])
            ->expectsOutputToContain('Added reply to ticket')
            ->assertSuccessful();

        unlink($path);

        $this->assertTrue($ticket->messages()->where('body', 'Piped from the mail server.')->exists());
    }

    public function test_parser_prefers_plain_text_and_decodes_quoted_printable(): void
    {
        $raw = <<<'EML'
From: Customer <customer@example.com>
To: support@example.com
Subject: Re: Ticket #12
Content-Type: multipart/alternative; boundary="bound"

--bound
Content-Type: text/plain; charset="UTF-8"
Content-Transfer-Encoding: quoted-printable

The disk is at 100=25.

--bound
Content-Type: text/html; charset="UTF-8"

<p>HTML should be ignored.</p>

--bound--
EML;

        $parsed = InboundMailParser::parse($raw);

        $this->assertSame('customer@example.com', $parsed->fromEmail);
        $this->assertSame('The disk is at 100%.', $parsed->body);
        $this->assertFalse($parsed->automatic);
    }

    protected function openTicket(): Ticket
    {
        return Ticket::actions()->createAsClient([
            'user_id' => $this->customer->id,
            'department_id' => $this->department->id,
            'title' => 'Cannot access my server',
            'body' => 'SSH fails.',
            'priority' => Ticket::PRIORITY_MEDIUM,
        ]);
    }

    /**
     * @param  array<string, string>  $extraHeaders
     */
    protected function rawReply(
        string $from,
        string $to,
        string $body,
        ?string $inReplyTo = null,
        array $extraHeaders = [],
    ): string {
        $headers = [
            'From' => $from,
            'To' => $to,
            'Subject' => 'Re: Ticket #1',
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            ...$extraHeaders,
        ];

        if ($inReplyTo) {
            $headers['In-Reply-To'] = '<'.$inReplyTo.'>';
        }

        if (! isset($headers['Message-ID'])) {
            $headers['Message-ID'] = '<'.uniqid('test-', true).'@example.com>';
        }

        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name.': '.$value;
        }

        return implode("\n", $lines)."\n\n".$body;
    }
}
