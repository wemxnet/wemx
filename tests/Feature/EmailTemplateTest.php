<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_sending_uses_the_default_template(): void
    {
        $user = User::factory()->create();

        $user->email([
            'identifier' => 'payment.paid',
            'variables' => [
                'description' => 'Invoice for hosting',
                'amount' => '$10.00',
                'transaction_id' => 'txn_123',
                'date' => '01 Jan 2026',
            ],
            'button' => [
                'url' => 'https://example.test/payments/view',
            ],
        ]);

        $email = Email::query()->first();

        $this->assertNotNull($email);
        $this->assertSame('payment.paid', $email->identifier);
        $this->assertSame('Payment was successfully processed', $email->subject);
        $this->assertContains('You are receiving this email because your payment was successfully processed.', $email->lines);
        $this->assertSame('View Invoice', $email->button_text);
        $this->assertSame('https://example.test/payments/view', $email->button_url);
    }

    public function test_customized_template_overrides_the_default_copy(): void
    {
        $user = User::factory()->create();

        EmailTemplate::actions()->updateAsAdmin([
            'identifier' => 'order.suspended',
            'subject' => '{{package_name}} is paused',
            'body' => "Hi {{user_name}},\n{{package_name}} (#{{order_id}}) was suspended.",
            'button_text' => 'Open service',
            'enabled' => true,
        ]);

        $user->email([
            'identifier' => 'order.suspended',
            'variables' => [
                'package_name' => 'Game Server',
                'order_id' => 42,
            ],
            'button' => [
                'url' => 'https://example.test/orders/42',
            ],
        ]);

        $email = Email::query()->first();

        $this->assertNotNull($email);
        $this->assertSame('Game Server is paused', $email->subject);
        $this->assertSame([
            'Hi '.$user->first_name.',',
            'Game Server (#42) was suspended.',
        ], $email->lines);
        $this->assertSame('Open service', $email->button_text);
    }

    public function test_disabled_template_does_not_send_an_email(): void
    {
        $user = User::factory()->create();

        EmailTemplate::actions()->updateAsAdmin([
            'identifier' => 'account.new-login',
            'subject' => 'New login to your account',
            'body' => 'There was a new login.',
            'button_text' => null,
            'enabled' => false,
        ]);

        $user->email([
            'identifier' => 'account.new-login',
        ]);

        $this->assertSame(0, Email::query()->count());
    }

    public function test_resetting_a_template_restores_the_default_copy(): void
    {
        EmailTemplate::actions()->updateAsAdmin([
            'identifier' => 'payment.paid',
            'subject' => 'Custom subject',
            'body' => 'Custom body',
            'button_text' => 'Pay',
            'enabled' => true,
        ]);

        $this->assertTrue(EmailTemplate::resolved('payment.paid')['customized']);

        EmailTemplate::actions()->resetAsAdmin([
            'identifier' => 'payment.paid',
        ]);

        $resolved = EmailTemplate::resolved('payment.paid');

        $this->assertFalse($resolved['customized']);
        $this->assertSame('Payment was successfully processed', $resolved['subject']);
        $this->assertStringContainsString('successfully processed', $resolved['body']);
    }

    public function test_one_off_emails_still_send_without_a_template(): void
    {
        $user = User::factory()->create();

        $user->email([
            'identifier' => 'custom.notice',
            'subject' => 'A custom notice',
            'lines' => ['This is a one-off email.'],
        ]);

        $email = Email::query()->first();

        $this->assertNotNull($email);
        $this->assertSame('custom.notice', $email->identifier);
        $this->assertSame('A custom notice', $email->subject);
        $this->assertSame(['This is a one-off email.'], $email->lines);
    }

    public function test_admin_can_save_a_template_from_the_edit_form(): void
    {
        $admin = User::factory()->create();

        $this->assertTrue($admin->isAdmin());

        $this->actingAs($admin);

        Volt::test('admin_area.default.emails.livewire.edit-template-form', ['template' => 'payment.paid'])
            ->set('subject', 'Thanks for your payment')
            ->set('body', 'We received {{amount}}.')
            ->set('buttonText', 'See invoice')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true)
            ->assertSet('customized', true);

        $template = EmailTemplate::query()->where('identifier', 'payment.paid')->first();

        $this->assertNotNull($template);
        $this->assertSame('Thanks for your payment', $template->subject);
        $this->assertSame('We received {{amount}}.', $template->body);
        $this->assertSame('See invoice', $template->button_text);
    }

    public function test_template_preview_renders_tables_as_html(): void
    {
        $admin = User::factory()->create(['username' => 'admin']);

        $this->actingAs($admin);

        $html = Volt::test('admin_area.default.emails.livewire.edit-template-form', ['template' => 'account.created'])
            ->instance()
            ->previewHtml();

        $this->assertStringContainsString('Username', $html);
        $this->assertStringContainsString('alex@example.com', $html);
        $this->assertStringContainsString('s3cretPass', $html);
        $this->assertStringContainsString('<th', $html);
        $this->assertStringContainsString('<td', $html);
        $this->assertStringNotContainsString('&lt;thead&gt;', $html);
        $this->assertStringNotContainsString('&lt;th', $html);
        $this->assertStringNotContainsString('<!--[if BLOCK]', $html);
        $this->assertSame(0, Email::query()->count());
    }

    public function test_template_preview_renders_sample_content_without_sending(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin);

        $html = Volt::test('admin_area.default.emails.livewire.edit-template-form', ['template' => 'payment.paid'])
            ->set('body', 'We received {{amount}} for {{description}}.')
            ->set('buttonText', 'See invoice')
            ->instance()
            ->previewHtml();

        $this->assertStringContainsString('We received $10.00', $html);
        $this->assertStringContainsString('Invoice for hosting', $html);
        $this->assertStringContainsString('See invoice', $html);
        $this->assertStringContainsString($admin->username, $html);
        $this->assertSame(0, Email::query()->count());
    }

    public function test_ticket_templates_compose_guest_notes_and_subjects(): void
    {
        $composed = EmailTemplate::compose([
            'template' => 'tickets.created',
            'identifier' => 'tickets.1.created.alex@example.com',
            'variables' => [
                'ticket_title' => 'Billing question',
                'ticket_number' => 'TKT-1',
                'department' => 'General',
                'preview' => 'How do I change my card?',
                'guest_note' => 'Use the button below to view and reply to your ticket.',
            ],
            'button_url' => 'https://example.test/tickets/guest/abc?member=xyz',
        ]);

        $this->assertIsArray($composed);
        $this->assertSame('Ticket opened: Billing question', $composed['subject']);
        $this->assertContains('A new support ticket TKT-1 was opened in General.', $composed['lines']);
        $this->assertContains('Use the button below to view and reply to your ticket.', $composed['lines']);
        $this->assertSame('View ticket', $composed['button_text']);
        $this->assertSame('tickets.1.created.alex@example.com', $composed['identifier']);
        $this->assertArrayNotHasKey('template', $composed);
        $this->assertArrayNotHasKey('variables', $composed);
    }
}
