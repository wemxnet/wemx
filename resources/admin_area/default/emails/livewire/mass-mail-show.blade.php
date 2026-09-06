<?php

use App\Models\MassMail;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component
{
    #[Locked]
    public MassMail $massMail;

    public function mount(MassMail $massMail): void
    {
        $this->massMail = $massMail;
    }

    public function cancel(): void
    {
        abort_if(! auth()->user()->hasPerm('admin.emails.mass-mails'), 403);

        MassMail::actions()->cancelAsAdmin([
            'mass_mail_id' => $this->massMail->id,
        ]);

        $this->massMail = $this->massMail->fresh();

        session()->flash('success', __('messages.mass_mail_cancelled'));
    }

    public function refreshCampaign(): void
    {
        $this->massMail = $this->massMail->fresh();
    }

    public function statusBadge(): string
    {
        return match ($this->massMail->status) {
            MassMail::STATUS_SENT => 'bg-green-lt',
            MassMail::STATUS_SENDING => 'bg-yellow-lt',
            MassMail::STATUS_QUEUED => 'bg-blue-lt',
            MassMail::STATUS_FAILED => 'bg-danger-lt',
            default => 'bg-secondary-lt',
        };
    }
}

?>

<div @if($massMail->isInProgress()) wire:poll.5s="refreshCampaign" @endif>
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">{{ __('messages.status') }}</div>
                    <div class="h3 mb-0">
                        <span class="badge {{ $this->statusBadge() }}">{{ ucfirst($massMail->status) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">{{ __('messages.mass_mail_recipients') }}</div>
                    <div class="h3 mb-0">{{ $massMail->recipient_count }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">{{ __('messages.mass_mail_sent') }}</div>
                    <div class="h3 mb-0">{{ $massMail->sent_count }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">{{ __('messages.mass_mail_failed') }}</div>
                    <div class="h3 mb-0">{{ $massMail->failed_count }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($massMail->recipient_count > 0)
        <div class="progress mb-3" style="height: 0.6rem;">
            <div
                class="progress-bar {{ $massMail->status === \App\Models\MassMail::STATUS_FAILED ? 'bg-danger' : 'bg-primary' }}"
                role="progressbar"
                style="width: {{ min(100, (int) round((($massMail->sent_count + $massMail->failed_count) / max(1, $massMail->recipient_count)) * 100)) }}%"
            ></div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">{{ $massMail->subject }}</h3>
            <div class="card-actions">
                @if($massMail->isCancellable())
                    <button type="button" class="btn btn-outline-danger" wire:click="cancel" wire:confirm="{{ __('messages.mass_mail_cancel_confirm') }}">
                        {{ __('messages.cancel') }}
                    </button>
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="mb-3">
                        <div class="subheader">{{ __('messages.mass_mail_audience') }}</div>
                        <div>{{ implode(' · ', $massMail->audienceSummary()) }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="subheader">{{ __('messages.mass_mail_scheduled_at') }}</div>
                        <div>{{ $massMail->scheduled_at?->translatedFormat('d M Y H:i') ?? '—' }}</div>
                    </div>
                    <div class="mb-0">
                        <div class="subheader">{{ __('messages.created_at') }}</div>
                        <div>{{ $massMail->created_at->translatedFormat('d M Y H:i') }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <div class="subheader">{{ __('messages.mass_mail_started_at') }}</div>
                        <div>{{ $massMail->started_at?->translatedFormat('d M Y H:i') ?? '—' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="subheader">{{ __('messages.mass_mail_completed_at') }}</div>
                        <div>{{ $massMail->completed_at?->translatedFormat('d M Y H:i') ?? '—' }}</div>
                    </div>
                    @if($massMail->last_error)
                        <div class="mb-0">
                            <div class="subheader">{{ __('messages.error') }}</div>
                            <div class="text-danger">{{ $massMail->last_error }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ __('messages.email_body') }}</h3>
        </div>
        <div class="card-body">
            <pre class="mb-0 text-wrap">{{ $massMail->body }}</pre>
            @if($massMail->button_text)
                <div class="mt-3">
                    <span class="badge bg-blue-lt">{{ $massMail->button_text }}</span>
                    <span class="text-secondary">{{ $massMail->button_url }}</span>
                </div>
            @endif
        </div>
    </div>
</div>
