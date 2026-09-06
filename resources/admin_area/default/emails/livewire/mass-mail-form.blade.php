<?php

use App\Facades\World;
use App\Mail\CustomerMail;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\MassMail;
use App\Models\Package;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component
{
    public string $audience_type = MassMail::AUDIENCE_ALL_CUSTOMERS;

    public ?string $package_id = null;

    public ?string $order_status = null;

    public ?string $user_status = null;

    public ?string $country = null;

    public bool $subscribed_only = false;

    public bool $verified_only = false;

    public string $subject = '';

    public string $body = '';

    public string $button_text = '';

    public string $button_url = '';

    public string $send_mode = 'now';

    public ?string $scheduled_at = null;

    public function updatedAudienceType(): void
    {
        if ($this->audience_type !== MassMail::AUDIENCE_WITH_PACKAGE) {
            $this->package_id = null;
        }

        if (! in_array($this->audience_type, [MassMail::AUDIENCE_WITH_PACKAGE, MassMail::AUDIENCE_WITH_ORDER_STATUS], true)) {
            $this->order_status = null;
        }

        if ($this->audience_type === MassMail::AUDIENCE_USER_STATUS) {
            $this->user_status = $this->user_status ?: 'active';
        }

        if ($this->audience_type === MassMail::AUDIENCE_BY_COUNTRY) {
            $this->country = $this->country ?: null;
        }
    }

    public function insertPlaceholder(string $placeholder): void
    {
        if (! array_key_exists($placeholder, MassMail::placeholderHints())) {
            return;
        }

        $token = '{{'.$placeholder.'}}';
        $this->body = $this->body === '' ? $token : rtrim($this->body)."\n".$token;
    }

    public function queue(): void
    {
        abort_if(! auth()->user()->hasPerm('admin.emails.mass-mails'), 403);

        $massMail = MassMail::actions()->queueAsAdmin([
            'created_by' => auth()->id(),
            'subject' => $this->subject,
            'body' => $this->body,
            'button_text' => $this->button_text,
            'button_url' => $this->button_url,
            'audience_type' => $this->audience_type,
            'package_id' => $this->package_id,
            'order_status' => $this->order_status,
            'user_status' => $this->user_status,
            'country' => $this->country,
            'subscribed_only' => $this->subscribed_only,
            'verified_only' => $this->verified_only,
            'scheduled_at' => $this->send_mode === 'scheduled' ? $this->scheduled_at : null,
        ]);

        session()->flash('success', $this->send_mode === 'scheduled'
            ? __('messages.mass_mail_scheduled')
            : __('messages.mass_mail_queued'));

        $this->redirect(route('admin.emails.mass-mails.show', $massMail), true);
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'package_id' => $this->package_id,
            'order_status' => $this->order_status,
            'user_status' => $this->user_status,
            'country' => $this->country,
            'subscribed_only' => $this->subscribed_only,
            'verified_only' => $this->verified_only,
        ];
    }

    public function recipientCount(): int
    {
        return MassMail::customersQuery($this->audience_type, $this->filters())->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function sampleRecipients()
    {
        return MassMail::customersQuery($this->audience_type, $this->filters())
            ->orderBy('users.id')
            ->limit(8)
            ->get(['users.id', 'users.first_name', 'users.last_name', 'users.username', 'users.email']);
    }

    public function previewHtml(): string
    {
        $previewUser = $this->sampleRecipients()->first() ?? auth()->user();
        $variables = MassMail::placeholderVariables($previewUser instanceof User ? $previewUser : null);

        $email = new Email([
            'subject' => EmailTemplate::replacePlaceholders(
                $this->subject !== '' ? $this->subject : __('messages.subject'),
                $variables
            ),
            'lines' => EmailTemplate::bodyToLines(
                EmailTemplate::replacePlaceholders($this->body, $variables)
            ),
            'button_text' => filled($this->button_text)
                ? EmailTemplate::replacePlaceholders($this->button_text, $variables)
                : null,
            'button_url' => filled($this->button_url)
                ? EmailTemplate::replacePlaceholders($this->button_url, $variables)
                : null,
        ]);

        if ($previewUser instanceof User) {
            $email->setRelation('user', $previewUser);
        }

        return (new CustomerMail($email))->render();
    }

    /**
     * @return array<int, string>
     */
    public function packages(): array
    {
        return Package::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, string>
     */
    public function countries(): array
    {
        return World::countries();
    }
}

?>

<div>
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">{{ __('messages.mass_mail_audience') }}</h3>
        </div>
        <div class="card-body">
            <div class="form-selectgroup-boxes row g-2">
                @foreach(\App\Models\MassMail::audienceOptions() as $type => $option)
                    <div class="col-md-6 col-xl-4" wire:key="audience-{{ $type }}">
                        <label class="form-selectgroup-item w-100 h-100 mb-0">
                            <input type="radio" class="form-selectgroup-input" wire:model.live="audience_type" value="{{ $type }}">
                            <span class="form-selectgroup-label d-flex align-items-start p-3 w-100 h-100 text-start">
                                <span>
                                    <span class="d-block fw-medium">{{ $option['label'] }}</span>
                                    <span class="d-block text-secondary small mt-1">{{ $option['description'] }}</span>
                                </span>
                            </span>
                        </label>
                    </div>
                @endforeach
            </div>

            <div class="row g-3 mt-1">
                @if($audience_type === \App\Models\MassMail::AUDIENCE_WITH_PACKAGE)
                    <div class="col-md-6">
                        <x-admin::form.label class="required">{{ __('messages.package') }}</x-admin::form.label>
                        <x-admin::form.select wire:model.live="package_id" :options="$this->packages()" name="package_id" />
                        @error('package_id')
                            <x-admin::form.error :message="$message" />
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <x-admin::form.label>{{ __('messages.mass_mail_order_status') }}</x-admin::form.label>
                        <select class="form-control" wire:model.live="order_status">
                            <option value="">{{ __('messages.mass_mail_any_status') }}</option>
                            @foreach(\App\Models\MassMail::ORDER_STATUSES as $status)
                                <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($audience_type === \App\Models\MassMail::AUDIENCE_WITH_ORDER_STATUS)
                    <div class="col-md-6">
                        <x-admin::form.label class="required">{{ __('messages.mass_mail_order_status') }}</x-admin::form.label>
                        <select class="form-control" wire:model.live="order_status">
                            <option value="">{{ __('messages.mass_mail_choose_status') }}</option>
                            @foreach(\App\Models\MassMail::ORDER_STATUSES as $status)
                                <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        @error('order_status')
                            <x-admin::form.error :message="$message" />
                        @enderror
                    </div>
                @endif

                @if($audience_type === \App\Models\MassMail::AUDIENCE_USER_STATUS)
                    <div class="col-md-6">
                        <x-admin::form.label class="required">{{ __('messages.mass_mail_user_status') }}</x-admin::form.label>
                        <select class="form-control" wire:model.live="user_status">
                            <option value="">{{ __('messages.mass_mail_choose_status') }}</option>
                            @foreach(\App\Models\MassMail::USER_STATUSES as $status)
                                <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        @error('user_status')
                            <x-admin::form.error :message="$message" />
                        @enderror
                    </div>
                @endif

                @if($audience_type === \App\Models\MassMail::AUDIENCE_BY_COUNTRY)
                    <div class="col-md-6">
                        <x-admin::form.label class="required">{{ __('messages.country') }}</x-admin::form.label>
                        <x-admin::form.select wire:model.live="country" :options="$this->countries()" name="country" />
                        @error('country')
                            <x-admin::form.error :message="$message" />
                        @enderror
                    </div>
                @endif

                @if($audience_type !== \App\Models\MassMail::AUDIENCE_USER_STATUS)
                    <div class="col-md-4">
                        <x-admin::form.label>{{ __('messages.mass_mail_user_status') }}</x-admin::form.label>
                        <select class="form-control" wire:model.live="user_status">
                            <option value="">{{ __('messages.mass_mail_any_status') }}</option>
                            @foreach(\App\Models\MassMail::USER_STATUSES as $status)
                                <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($audience_type !== \App\Models\MassMail::AUDIENCE_BY_COUNTRY)
                    <div class="col-md-4">
                        <x-admin::form.label>{{ __('messages.country') }}</x-admin::form.label>
                        <x-admin::form.select wire:model.live="country" :options="$this->countries()" name="country" />
                    </div>
                @endif
            </div>

            <div class="d-flex flex-wrap gap-4 mt-3">
                @if($audience_type !== \App\Models\MassMail::AUDIENCE_SUBSCRIBED)
                    <label class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" wire:model.live="subscribed_only">
                        <span class="form-check-label">{{ __('messages.mass_mail_subscribed_only') }}</span>
                    </label>
                @endif
                <label class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" wire:model.live="verified_only">
                    <span class="form-check-label">{{ __('messages.mass_mail_verified_only') }}</span>
                </label>
            </div>

            @error('audience_type')
                <x-admin::form.error :message="$message" />
            @enderror
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('messages.mass_mail_compose') }}</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <x-admin::form.label class="required">{{ __('messages.subject') }}</x-admin::form.label>
                        <x-admin::form.input type="text" wire:model.live.debounce.400ms="subject" name="subject" placeholder="{{ __('messages.subject') }}" />
                        @error('subject')
                            <x-admin::form.error :message="$message" />
                        @enderror
                    </div>
                    <div class="mb-3">
                        <x-admin::form.label class="required">{{ __('messages.email_body') }}</x-admin::form.label>
                        <x-admin::form.textarea wire:model.live.debounce.400ms="body" rows="14" placeholder="{{ __('messages.mass_mail_body_placeholder') }}" />
                        @error('body')
                            <x-admin::form.error :message="$message" />
                        @else
                            <x-admin::form.description>{{ __('messages.mass_mail_body_hint') }}</x-admin::form.description>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <x-admin::form.label>{{ __('messages.email_template_placeholders') }}</x-admin::form.label>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach(\App\Models\MassMail::placeholderHints() as $placeholder => $hint)
                                <button type="button" class="btn btn-sm" wire:click="insertPlaceholder('{{ $placeholder }}')" title="{{ $hint }}">
                                    {{ '{'.'{'.$placeholder.'}'.'}' }}
                                </button>
                            @endforeach
                        </div>
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
                    <div class="mb-0">
                        <x-admin::form.label>{{ __('messages.mass_mail_when') }}</x-admin::form.label>
                        <div class="d-flex flex-wrap gap-3 mb-2">
                            <label class="form-check">
                                <input class="form-check-input" type="radio" wire:model.live="send_mode" value="now">
                                <span class="form-check-label">{{ __('messages.mass_mail_send_now') }}</span>
                            </label>
                            <label class="form-check">
                                <input class="form-check-input" type="radio" wire:model.live="send_mode" value="scheduled">
                                <span class="form-check-label">{{ __('messages.mass_mail_schedule') }}</span>
                            </label>
                        </div>
                        @if($send_mode === 'scheduled')
                            <x-admin::form.input type="datetime-local" wire:model.live="scheduled_at" name="scheduled_at" />
                            @error('scheduled_at')
                                <x-admin::form.error :message="$message" />
                            @enderror
                        @endif
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="{{ route('admin.emails.mass-mails.index') }}" class="btn" wire:navigate>{{ __('messages.cancel') }}</a>
                    <button
                        type="button"
                        class="btn btn-primary"
                        wire:click="queue"
                        wire:confirm="{{ __('messages.mass_mail_confirm', ['count' => $this->recipientCount()]) }}"
                        wire:loading.attr="disabled"
                        @disabled($this->recipientCount() === 0)
                    >
                        <span wire:loading.remove wire:target="queue">
                            {{ $send_mode === 'scheduled' ? __('messages.mass_mail_schedule') : __('messages.mass_mail_queue_send') }}
                        </span>
                        <span wire:loading wire:target="queue">{{ __('messages.loading') }}</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">{{ __('messages.mass_mail_recipients') }}</h3>
                    <div class="card-actions">
                        <span class="badge bg-blue-lt">{{ trans_choice('messages.mass_mail_recipient_count', $this->recipientCount(), ['count' => $this->recipientCount()]) }}</span>
                    </div>
                </div>
                <div class="card-body py-2">
                    @forelse($this->sampleRecipients() as $recipient)
                        <div class="d-flex align-items-center py-2 @if(! $loop->last) border-bottom @endif" wire:key="recipient-{{ $recipient->id }}">
                            <span class="avatar avatar-2 me-2" style="background-image: url('{{ $recipient->getAvatarUrl() }}')"></span>
                            <div class="flex-fill">
                                <div class="fw-medium">{{ $recipient->full_name }} ({{ $recipient->username }})</div>
                                <div class="text-secondary small">{{ $recipient->email }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-secondary py-3">{{ __('messages.mass_mail_no_recipients') }}</div>
                    @endforelse
                    @if($this->recipientCount() > 8)
                        <div class="text-secondary small pt-2">{{ __('messages.mass_mail_more_recipients', ['count' => $this->recipientCount() - 8]) }}</div>
                    @endif
                </div>
            </div>
            <div class="card sticky-top" style="top: 1rem;">
                <div class="card-header">
                    <h3 class="card-title">{{ __('messages.email_preview') }}</h3>
                </div>
                <div class="card-body p-0 bg-light">
                    <iframe
                        wire:key="email-preview-{{ md5($subject.$body.$button_text.$button_url.($this->sampleRecipients()->first()?->id ?? 'none')) }}"
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
