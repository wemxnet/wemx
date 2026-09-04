<?php

use App\Mail\CustomerMail;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component
{
    #[Locked]
    public User $user;

    public string $subject = '';

    public string $body = '';

    public string $button_text = '';

    public string $button_url = '';

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function send(): void
    {
        abort_if(! auth()->user()->hasPerm('admin.users.update'), 403);

        User::actions()->sendEmailAsAdmin([
            'user_id' => $this->user->id,
            'subject' => $this->subject,
            'body' => $this->body,
            'button_text' => $this->button_text,
            'button_url' => $this->button_url,
        ]);

        session()->flash('success', __('messages.email_sent_successfully'));

        $this->redirect(route('admin.users.edit', [
            'user' => $this->user->id,
            'userEditPage' => 'email-history',
        ]), true);
    }

    public function previewHtml(): string
    {
        $email = new Email([
            'subject' => $this->subject !== '' ? $this->subject : __('messages.subject'),
            'lines' => EmailTemplate::bodyToLines($this->body),
            'button_text' => filled($this->button_text) ? $this->button_text : null,
            'button_url' => filled($this->button_url) ? $this->button_url : null,
        ]);

        $email->setRelation('user', $this->user);

        return (new CustomerMail($email))->render();
    }
}

?>

<div>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('messages.send_email') }}</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <x-admin::form.label>{{ __('messages.email_to') }}</x-admin::form.label>
                        <x-admin::form.input type="text" :value="$user->email" disabled />
                        <x-admin::form.description>{{ $user->full_name }} ({{ $user->username }})</x-admin::form.description>
                    </div>
                    <div class="mb-3">
                        <x-admin::form.label class="required">{{ __('messages.subject') }}</x-admin::form.label>
                        <x-admin::form.input type="text" wire:model.live.debounce.400ms="subject" name="subject" placeholder="{{ __('messages.subject') }}" />
                        @error('subject')
                            <x-admin::form.error :message="$message" />
                        @enderror
                    </div>
                    <div class="mb-3">
                        <x-admin::form.label class="required">{{ __('messages.email_body') }}</x-admin::form.label>
                        <x-admin::form.textarea wire:model.live.debounce.400ms="body" rows="16" placeholder="Write the email body. Markdown is supported." />
                        @error('body')
                            <x-admin::form.error :message="$message" />
                        @else
                            <x-admin::form.description>Each line is sent as markdown. Leave a blank line to start a new paragraph.</x-admin::form.description>
                        @enderror
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <x-admin::form.label>{{ __('messages.button_text') }}</x-admin::form.label>
                            <x-admin::form.input type="text" wire:model.live.debounce.400ms="button_text" name="button_text" placeholder="View account" />
                            @error('button_text')
                                <x-admin::form.error :message="$message" />
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <x-admin::form.label>{{ __('messages.button_url') }}</x-admin::form.label>
                            <x-admin::form.input type="url" wire:model.live.debounce.400ms="button_url" name="button_url" placeholder="https://" />
                            @error('button_url')
                                <x-admin::form.error :message="$message" />
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <a href="{{ route('admin.users.edit', $user->id) }}" class="btn me-2" wire:navigate>{{ __('messages.cancel') }}</a>
                    <button type="button" class="btn btn-primary" wire:click="send" wire:confirm="Send this email to {{ $user->email }}?" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="send">{{ __('messages.send_email') }}</span>
                        <span wire:loading wire:target="send">{{ __('messages.loading') }}</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card sticky-top" style="top: 1rem;">
                <div class="card-header">
                    <h3 class="card-title">{{ __('messages.email_preview') }}</h3>
                </div>
                <div class="card-body p-0 bg-light">
                    <iframe
                        wire:key="email-preview-{{ md5($subject.$body.$button_text.$button_url) }}"
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
