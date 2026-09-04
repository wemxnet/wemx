<?php

use App\Mail\CustomerMail;
use App\Models\Email;
use App\Models\EmailTemplate;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component
{
    public string $template = '';

    public string $name = '';

    public string $group = '';

    public string $description = '';

    public string $subject = '';

    public string $body = '';

    public string $buttonText = '';

    public bool $enabled = true;

    public bool $customized = false;

    public bool $saved = false;

    public array $placeholders = [];

    public function mount(string $template): void
    {
        abort_unless(EmailTemplate::definitionExists($template), 404);

        $this->template = $template;
        $this->fillFromResolved();
    }

    public function save(): void
    {
        EmailTemplate::actions()->updateAsAdmin([
            'identifier' => $this->template,
            'subject' => $this->subject,
            'body' => $this->body,
            'button_text' => $this->buttonText,
            'enabled' => $this->enabled,
        ]);

        $this->fillFromResolved();
        $this->saved = true;
    }

    public function resetToDefault(): void
    {
        EmailTemplate::actions()->resetAsAdmin([
            'identifier' => $this->template,
        ]);

        $this->fillFromResolved();
        $this->saved = false;
    }

    public function previewHtml(): string
    {
        $variables = $this->sampleVariables();
        $subject = EmailTemplate::replacePlaceholders(
            $this->subject !== '' ? $this->subject : __('messages.subject'),
            $variables
        );
        $buttonText = EmailTemplate::replacePlaceholders($this->buttonText, $variables);

        $email = new Email([
            'subject' => $subject,
            'lines' => EmailTemplate::bodyToLines(
                EmailTemplate::replacePlaceholders($this->body, $variables)
            ),
            'button_text' => filled($buttonText) ? $buttonText : null,
            'button_url' => filled($buttonText) ? url('/') : null,
            'table' => $this->previewTable(),
        ]);

        if (auth()->user()) {
            $email->setRelation('user', auth()->user());
        }

        return (new CustomerMail($email))->render();
    }

    private function fillFromResolved(): void
    {
        $resolved = EmailTemplate::resolved($this->template);

        $this->name = $resolved['name'];
        $this->group = $resolved['group'];
        $this->description = $resolved['description'];
        $this->subject = $resolved['subject'];
        $this->body = $resolved['body'];
        $this->buttonText = (string) ($resolved['button_text'] ?? '');
        $this->enabled = $resolved['enabled'];
        $this->customized = $resolved['customized'];
        $this->placeholders = array_merge([
            'app_name' => 'Application name',
            'user_name' => 'Recipient first name',
            'user_username' => 'Recipient username',
            'user_email' => 'Recipient email address',
        ], $resolved['placeholders']);
    }

    /**
     * @return array<string, string>
     */
    private function sampleVariables(): array
    {
        $user = auth()->user();

        $variables = [
            'app_name' => settings('app_name', 'My Application'),
            'user_name' => $user?->first_name ?: 'Alex',
            'user_username' => $user?->username ?: 'alex',
            'user_email' => $user?->email ?: 'alex@example.com',
        ];

        foreach (array_keys($this->placeholders) as $key) {
            if (! array_key_exists($key, $variables)) {
                $variables[$key] = $this->sampleValueFor($key);
            }
        }

        return $variables;
    }

    private function sampleValueFor(string $key): string
    {
        $date = now()->format(settings('date_format', 'd M Y H:i'));

        return match ($key) {
            'package_name' => 'VPS Starter',
            'order_id', 'count' => '42',
            'cycle' => '$9.99 / Monthly',
            'status' => 'Active',
            'due_date', 'date' => $date,
            'description' => 'Invoice for hosting',
            'amount' => '$10.00',
            'transaction_id' => 'txn_123456',
            'gateway' => 'Stripe',
            'subscription_id' => 'sub_123456',
            'ticket_title' => 'Cannot access my server',
            'ticket_number' => 'TKT-1',
            'department' => 'General',
            'preview' => 'SSH fails with permission denied.',
            'guest_note' => 'Use the button below to view and reply to your ticket.',
            'author_name', 'requester_name', 'inviter_name', 'username' => 'Alex',
            'close_message' => 'Ticket TKT-1 has been closed.',
            'new_email', 'member_email', 'panel_email' => 'alex@example.com',
            'message' => 'Your due date has been extended.',
            'grace_period' => '3',
            'panel_password' => 's3cretPass',
            'active_until' => 'Your subscription will remain active until next month.',
            default => Str::headline(str_replace('_', ' ', $key)),
        };
    }

    /**
     * @return array{columns: array<int, string>, rows: array<int, array<int, string>>}|null
     */
    private function previewTable(): ?array
    {
        $date = now()->format(settings('date_format', 'd M Y H:i'));

        return match (true) {
            $this->template === 'account.created' => [
                'columns' => ['Username', 'Email', 'Password'],
                'rows' => [['alex', 'alex@example.com', 's3cretPass']],
            ],
            $this->group === 'Orders' => [
                'columns' => ['Package', 'Cycle', 'Status', 'Due Date'],
                'rows' => [['VPS Starter', '$9.99 / Monthly', 'Active', $date]],
            ],
            $this->group === 'Payments' => [
                'columns' => ['Description', 'Amount', 'Transaction ID', 'Date'],
                'rows' => [['Invoice for hosting', '$10.00', 'txn_123456', $date]],
            ],
            $this->group === 'Subscriptions' => [
                'columns' => ['Description', 'Amount', 'Gateway', 'Subscription ID'],
                'rows' => [['Game Server', '$10.00 / Monthly', 'Stripe', 'sub_123456']],
            ],
            $this->group === 'Admin' => [
                'columns' => ['Order ID', 'User', 'Package', 'Due Date'],
                'rows' => [['42', 'alex@example.com (ID: 1)', 'VPS Starter', now()->toDateString()]],
            ],
            default => null,
        };
    }
};
?>

<div>
    <div class="row g-3">
        <div class="col-lg-6">
            <form class="card" wire:submit="save()">
                <div class="card-header">
                    <div>
                        <h3 class="card-title mb-0">{{ $name }}</h3>
                        <div class="text-secondary">{{ $group }} &middot; <code>{{ $template }}</code></div>
                    </div>
                    <div class="card-actions">
                        @if($customized)
                            <span class="badge bg-blue-lt">{{ __('messages.email_template_customized') }}</span>
                        @else
                            <span class="badge bg-secondary-lt">{{ __('messages.email_template_default') }}</span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    @if($saved)
                        <div class="alert alert-success">{{ __('messages.email_template_saved') }}</div>
                    @endif

                    <p class="text-secondary">{{ $description }}</p>

                    <div class="mb-3">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" wire:model.live="enabled" id="enabled-input">
                            <span class="form-check-label">{{ __('messages.enabled') }} — send this email when the event happens</span>
                        </label>
                    </div>

                    <div class="mb-3">
                        <x-admin::form.label class="required">{{ __('messages.subject') }}</x-admin::form.label>
                        <x-admin::form.input type="text" wire:model.live.debounce.400ms="subject" name="subject" />
                        @error('subject')
                            <x-admin::form.error :message="$message" />
                        @enderror
                    </div>

                    <div class="mb-3">
                        <x-admin::form.label class="required">{{ __('messages.email_template_body') }}</x-admin::form.label>
                        <x-admin::form.textarea wire:model.live.debounce.400ms="body" rows="12" />
                        @error('body')
                            <x-admin::form.error :message="$message" />
                        @else
                            <x-admin::form.description :description="__('messages.email_template_body_hint')" />
                        @enderror
                    </div>

                    <div class="mb-3">
                        <x-admin::form.label>{{ __('messages.button_text') }}</x-admin::form.label>
                        <x-admin::form.input type="text" wire:model.live.debounce.400ms="buttonText" name="button_text" />
                        @error('button_text')
                            <x-admin::form.error :message="$message" />
                        @else
                            <x-admin::form.description :description="__('messages.email_template_button_hint')" />
                        @enderror
                    </div>

                    <div class="mb-0">
                        <x-admin::form.label>{{ __('messages.email_template_placeholders') }}</x-admin::form.label>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($placeholders as $placeholder => $placeholderDescription)
                                <span class="badge bg-secondary-lt" title="{{ $placeholderDescription }}">{{ '{'.'{'.$placeholder.'}'.'}' }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('admin.emails.templates.index') }}" class="btn btn-link" wire:navigate>{{ __('messages.back') }}</a>
                    <div class="d-flex gap-2">
                        @if($customized)
                            <button type="button" class="btn" wire:click="resetToDefault" onclick="return confirm('{{ __('messages.email_template_reset_confirm') }}')">
                                {{ __('messages.email_template_reset') }}
                            </button>
                        @endif
                        <button type="submit" class="btn btn-primary">{{ __('messages.update') }}</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="col-lg-6">
            <div class="card sticky-top" style="top: 1rem;">
                <div class="card-header">
                    <h3 class="card-title">{{ __('messages.email_preview') }}</h3>
                </div>
                @unless($enabled)
                    <div class="alert alert-warning m-3 mb-0">This email is disabled and will not be sent.</div>
                @endunless
                <div class="card-body p-0 bg-light">
                    <iframe
                        wire:key="email-preview-{{ md5($subject.$body.$buttonText) }}"
                        title="{{ __('messages.email_preview') }}"
                        class="border-0 w-100 d-block"
                        style="min-height: 36rem; background: #f8fafc;"
                        srcdoc="{{ $this->previewHtml() }}"
                    ></iframe>
                </div>
            </div>
        </div>
    </div>
</div>
