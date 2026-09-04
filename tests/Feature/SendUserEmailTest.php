<?php

namespace Tests\Feature;

use App\Jobs\DeliverCustomerMail;
use App\Models\Email;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SendUserEmailTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->customer = User::factory()->create(['status' => 'active']);
    }

    public function test_admin_can_send_a_manual_email_to_a_user(): void
    {
        $this->actingAs($this->admin);

        Volt::test('admin_area.default.users.livewire.send-user-email', ['user' => $this->customer])
            ->set('subject', 'Account notice')
            ->set('body', "Hello,\n\nYour account is ready.\n\n**Thanks**")
            ->set('button_text', 'Open dashboard')
            ->set('button_url', 'https://example.com/dashboard')
            ->call('send')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.users.edit', [
                'user' => $this->customer->id,
                'userEditPage' => 'email-history',
            ]));

        $email = Email::query()->first();

        $this->assertNotNull($email);
        $this->assertSame($this->customer->id, $email->user_id);
        $this->assertSame($this->customer->email, $email->to);
        $this->assertSame('Account notice', $email->subject);
        $this->assertSame('admin.manual-email', $email->identifier);
        $this->assertSame([
            'Hello,',
            '',
            'Your account is ready.',
            '',
            '**Thanks**',
        ], $email->lines);
        $this->assertSame('Open dashboard', $email->button_text);
        $this->assertSame('https://example.com/dashboard', $email->button_url);

        Queue::assertPushed(DeliverCustomerMail::class);
    }

    public function test_preview_renders_the_composed_email(): void
    {
        $this->actingAs($this->admin);

        $html = Volt::test('admin_area.default.users.livewire.send-user-email', ['user' => $this->customer])
            ->set('subject', 'Welcome')
            ->set('body', 'Your server is **online**.')
            ->instance()
            ->previewHtml();

        $this->assertStringContainsString('Your server is', $html);
        $this->assertStringContainsString($this->customer->username, $html);
        $this->assertSame(0, Email::query()->count());
    }

    public function test_subject_and_body_are_required(): void
    {
        $this->actingAs($this->admin);

        Volt::test('admin_area.default.users.livewire.send-user-email', ['user' => $this->customer])
            ->set('subject', '')
            ->set('body', '')
            ->call('send')
            ->assertHasErrors(['subject', 'body']);

        $this->assertSame(0, Email::query()->count());
    }

    public function test_button_url_is_required_when_button_text_is_present(): void
    {
        $this->actingAs($this->admin);

        Volt::test('admin_area.default.users.livewire.send-user-email', ['user' => $this->customer])
            ->set('subject', 'Hello')
            ->set('body', 'Please click below.')
            ->set('button_text', 'Open')
            ->set('button_url', '')
            ->call('send')
            ->assertHasErrors(['button_url']);

        $this->assertSame(0, Email::query()->count());
    }

    public function test_user_without_permission_cannot_send_email(): void
    {
        $this->actingAs($this->customer);

        Volt::test('admin_area.default.users.livewire.send-user-email', ['user' => $this->admin])
            ->set('subject', 'Hello')
            ->set('body', 'This should not send.')
            ->call('send')
            ->assertForbidden();

        $this->assertSame(0, Email::query()->count());
    }

    public function test_send_email_as_admin_uses_user_email(): void
    {
        User::actions()->sendEmailAsAdmin([
            'user_id' => $this->customer->id,
            'subject' => 'Direct send',
            'body' => 'Sent from the action.',
        ]);

        $email = Email::query()->first();

        $this->assertNotNull($email);
        $this->assertSame('Direct send', $email->subject);
        $this->assertSame(['Sent from the action.'], $email->lines);
        $this->assertNull($email->button_text);
        $this->assertNull($email->button_url);
    }

    public function test_send_email_as_admin_rejects_whitespace_only_body(): void
    {
        $this->expectException(ValidationException::class);

        User::actions()->sendEmailAsAdmin([
            'user_id' => $this->customer->id,
            'subject' => 'Empty body',
            'body' => "   \n\n  ",
        ]);
    }
}
