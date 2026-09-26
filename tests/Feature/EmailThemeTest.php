<?php

namespace Tests\Feature;

use App\Mail\CustomerMail;
use App\Mail\EmailTheme;
use App\Models\Email;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EmailThemeTest extends TestCase
{
    use RefreshDatabase;

    private ?string $temporaryTheme = null;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    protected function tearDown(): void
    {
        if ($this->temporaryTheme !== null) {
            File::deleteDirectory($this->temporaryTheme);
        }

        parent::tearDown();
    }

    public function test_installed_themes_are_discovered(): void
    {
        $themes = EmailTheme::all();

        $this->assertArrayHasKey('default', $themes);
        $this->assertSame('Default', $themes['default']->name);
        $this->assertSame('The built-in transactional email layout.', $themes['default']->description);
        $this->assertArrayHasKey('dark', $themes);
        $this->assertSame('Dark', $themes['dark']->name);
        $this->assertTrue($themes['default']->usesMarkdown());
        $this->assertTrue($themes['dark']->usesMarkdown());
        $this->assertArrayHasKey('client-dark', $themes);
        $this->assertSame('Client Dark', $themes['client-dark']->name);
        $this->assertTrue($themes['client-dark']->usesMarkdown());
    }

    public function test_default_theme_uses_the_original_mail_layout(): void
    {
        $email = new Email([
            'subject' => 'Hello',
            'lines' => ['Welcome **there**'],
            'table' => [
                'columns' => ['Description', 'Amount'],
                'rows' => [['Invoice for hosting', '$10.00']],
            ],
            'button_text' => 'Open dashboard',
            'button_url' => 'https://example.com/open',
            'theme' => 'default',
        ]);
        $email->setRelation('user', new User(['username' => 'ada']));

        $html = (new CustomerMail($email))->render();

        $this->assertStringContainsString('Hi ada', $html);
        $this->assertStringContainsString('there</strong>', $html);
        $this->assertStringContainsString('button-primary', $html);
        $this->assertStringContainsString('All rights reserved', $html);
        $this->assertStringContainsString('Invoice for hosting', $html);
        $this->assertStringContainsString('https://example.com/open', $html);
    }

    public function test_dark_theme_renders_a_dark_layout(): void
    {
        $email = new Email([
            'subject' => 'Hello',
            'lines' => ['Welcome **there**'],
            'button_text' => 'Open dashboard',
            'button_url' => 'https://example.com/open',
            'theme' => 'dark',
        ]);
        $email->setRelation('user', new User(['username' => 'ada']));

        $html = (new CustomerMail($email))->render();

        $this->assertStringContainsString('color-scheme" content="dark"', $html);
        $this->assertStringContainsString('#09090b', $html);
        $this->assertStringContainsString('#18181b', $html);
        $this->assertStringContainsString('Hi ada', $html);
        $this->assertStringContainsString('there</strong>', $html);
        $this->assertStringContainsString('button-primary', $html);
        $this->assertStringContainsString('All rights reserved', $html);
        $this->assertStringContainsString('https://example.com/open', $html);
        $this->assertStringContainsString('Open dashboard', $html);
    }

    public function test_client_dark_theme_uses_client_area_colors(): void
    {
        $email = new Email([
            'subject' => 'Hello',
            'lines' => ['Welcome **there**'],
            'button_text' => 'Open dashboard',
            'button_url' => 'https://example.com/open',
            'theme' => 'client-dark',
        ]);
        $email->setRelation('user', new User(['username' => 'ada']));

        $html = (new CustomerMail($email))->render();

        $this->assertStringContainsString('color-scheme" content="dark"', $html);
        $this->assertStringContainsString('#11111d', $html);
        $this->assertStringContainsString('#171725', $html);
        $this->assertStringContainsString('#2563eb', $html);
        $this->assertStringContainsString('Hi ada', $html);
        $this->assertStringContainsString('there</strong>', $html);
        $this->assertStringContainsString('button-primary', $html);
        $this->assertStringContainsString('All rights reserved', $html);
        $this->assertStringContainsString('Open dashboard', $html);
    }

    public function test_emails_store_the_configured_default_theme(): void
    {
        $this->installTemporaryTheme();
        settings(['email_theme' => 'accent']);

        $email = Email::query()->create([
            'to' => 'person@example.com',
            'subject' => 'Hello',
            'lines' => ['Welcome'],
        ]);

        $this->assertSame('accent', $email->theme);
    }

    public function test_an_email_can_use_a_specific_theme(): void
    {
        $this->installTemporaryTheme();

        $email = Email::query()->create([
            'to' => 'person@example.com',
            'subject' => 'Hello',
            'lines' => ['Welcome **there**'],
            'button_text' => 'Open',
            'button_url' => 'https://example.com/open',
            'theme' => 'accent',
        ]);

        $html = (new CustomerMail($email))->render();

        $this->assertStringContainsString('id="accent-theme"', $html);
        $this->assertStringContainsString('Welcome', $html);
        $this->assertStringContainsString('<strong>there</strong>', $html);
        $this->assertStringContainsString('https://example.com/open', $html);
        $this->assertStringContainsString('Open', $html);
    }

    public function test_a_missing_theme_falls_back_when_rendering(): void
    {
        $this->installTemporaryTheme();

        $email = Email::query()->create([
            'to' => 'person@example.com',
            'subject' => 'Hello',
            'lines' => ['Welcome'],
            'theme' => 'accent',
        ]);

        File::deleteDirectory($this->temporaryTheme);
        $this->temporaryTheme = null;

        $html = (new CustomerMail($email->fresh()))->render();

        $this->assertStringContainsString('Hi', $html);
        $this->assertStringNotContainsString('accent-theme', $html);
    }

    public function test_unknown_themes_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Email::actions()->sendEmailToAddress([
            'to' => 'person@example.com',
            'subject' => 'Hello',
            'lines' => ['Hi'],
            'theme' => 'not-installed',
        ]);
    }

    public function test_application_settings_reject_an_unknown_email_theme(): void
    {
        try {
            Setting::actions()->updateApplicationSettingsAsAdmin([
                'app_name' => 'WemX',
                'language' => 'en',
                'currency' => 'USD',
                'timezone' => 'UTC',
                'email_theme' => 'not-installed',
            ]);
            $this->fail('An unknown email theme should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email_theme', $exception->errors());
        }
    }

    public function test_admin_can_set_the_default_email_theme(): void
    {
        $this->installTemporaryTheme();

        $env = base_path('.env');
        $backup = file_get_contents($env);

        try {
            $admin = User::factory()->create([
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            $this->actingAs($admin);

            Volt::test('admin_area.default.settings.livewire.application')
                ->assertSee('Default Email Theme')
                ->assertSee('Accent')
                ->set('email_theme', 'accent')
                ->call('saveChanges')
                ->assertHasNoErrors();

            $this->assertSame('accent', settings('email_theme'));
        } finally {
            file_put_contents($env, $backup);
        }
    }

    public function test_application_settings_page_lists_email_themes(): void
    {
        $this->installTemporaryTheme();

        config([
            'app.installed' => true,
            'app.license_key' => 'WMX-TESTING-KEY',
        ]);
        Cache::put('lcs_checked_at', now(), 21600);

        $admin = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()])
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Default Email Theme')
            ->assertSee('Accent');
    }

    private function installTemporaryTheme(): void
    {
        $path = resource_path('email_templates/accent');
        File::ensureDirectoryExists($path);
        File::put($path.'/theme.json', json_encode([
            'name' => 'Accent',
            'description' => 'A temporary theme used by tests.',
        ]));
        File::put($path.'/email.blade.php', <<<'BLADE'
<div id="accent-theme">{{ $name }}</div>
<div>{!! $body !!}</div>
@if (! empty($button['url']))
<a href="{{ $button['url'] }}">{{ $button['text'] }}</a>
@endif
BLADE);
        $this->temporaryTheme = $path;
    }
}
