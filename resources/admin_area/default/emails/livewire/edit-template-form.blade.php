<?php

use App\Models\EmailTemplate;
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
};
?>

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

        <div class="mb-3 row">
            <label class="col-3 col-form-label" for="enabled-input">{{ __('messages.enabled') }}</label>
            <div class="col">
                <label class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" wire:model="enabled" id="enabled-input">
                    <span class="form-check-label">Send this email when the event happens</span>
                </label>
            </div>
        </div>

        <div class="mb-3 row">
            <label class="col-3 col-form-label required" for="subject-input">{{ __('messages.subject') }}</label>
            <div class="col">
                <input type="text" wire:model="subject" class="form-control @error('subject') is-invalid @enderror" id="subject-input">
                @error('subject')
                    <x-admin::form.error :message="$message" />
                @enderror
            </div>
        </div>

        <div class="mb-3 row">
            <label class="col-3 col-form-label required" for="body-input">{{ __('messages.email_template_body') }}</label>
            <div class="col">
                <textarea wire:model="body" rows="8" class="form-control @error('body') is-invalid @enderror" id="body-input"></textarea>
                @error('body')
                    <x-admin::form.error :message="$message" />
                @else
                    <x-admin::form.description :description="__('messages.email_template_body_hint')" />
                @enderror
            </div>
        </div>

        <div class="mb-3 row">
            <label class="col-3 col-form-label" for="button-text-input">{{ __('messages.button_text') }}</label>
            <div class="col">
                <input type="text" wire:model="buttonText" class="form-control @error('button_text') is-invalid @enderror" id="button-text-input">
                @error('button_text')
                    <x-admin::form.error :message="$message" />
                @else
                    <x-admin::form.description :description="__('messages.email_template_button_hint')" />
                @enderror
            </div>
        </div>

        <div class="mb-0 row">
            <label class="col-3 col-form-label">{{ __('messages.email_template_placeholders') }}</label>
            <div class="col">
                <div class="d-flex flex-wrap gap-2">
                    @foreach($placeholders as $placeholder => $placeholderDescription)
                        <span class="badge bg-secondary-lt" title="{{ $placeholderDescription }}">{{ '{'.'{'.$placeholder.'}'.'}' }}</span>
                    @endforeach
                </div>
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
