<?php

use Extensions\Modules\Tickets\Support\TicketInboundMail;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component
{
    public string $mailbox = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission('admin.ticket-departments'), 403);

        $this->mailbox = (string) settings('tickets_inbound_mailbox', '');
    }

    public function webhookUrl(): string
    {
        return url('/tickets/inbound-mail').'?token='.urlencode(TicketInboundMail::webhookToken());
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasPermission('admin.ticket-departments'), 403);

        $validated = $this->validate([
            'mailbox' => ['nullable', 'email', 'max:255'],
        ]);

        settings([
            'tickets_inbound_mailbox' => $validated['mailbox'] ?: '',
        ]);

        $this->dispatch('alert', type: 'success', message: 'Email reply settings saved.');
    }

    public function rotateWebhookToken(): void
    {
        abort_unless(auth()->user()?->hasPermission('admin.ticket-departments'), 403);

        settings([
            'tickets_inbound_webhook_token' => Str::random(48),
        ]);

        $this->dispatch('alert', type: 'success', message: 'Inbound webhook token rotated.');
    }
}

?>

<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">Email replies</h3>
    </div>
    <form class="card-body" wire:submit="save">
        <p class="text-secondary">
            Ticket emails include a unique Reply-To address so customers and staff can reply from their own inbox.
            Pipe those messages into WemX with the webhook or artisan command below — no third-party mail service is required.
        </p>
        <div class="mb-3">
            <label class="form-label">Inbound mailbox</label>
            <input type="email" class="form-control @error('mailbox') is-invalid @enderror" wire:model="mailbox" placeholder="{{ config('mail.from.address') }}">
            @error('mailbox') <x-admin::form.error :message="$message"/> @enderror
            <small class="form-hint">
                Replies are addressed to this mailbox using plus-tags (for example <code>support+t12.ab12cd34@yourdomain.com</code>).
                Leave empty to use the application From address. Forward or pipe this mailbox into the webhook or command.
            </small>
        </div>
        <div class="mb-3">
            <label class="form-label">Webhook URL</label>
            <input type="text" class="form-control" readonly value="{{ $this->webhookUrl() }}" onclick="this.select()">
            <small class="form-hint">
                POST the raw RFC822 message as the request body. You can also send the token in the
                <code>X-Tickets-Inbound-Token</code> or <code>Authorization: Bearer</code> header.
            </small>
        </div>
        <div class="mb-3">
            <label class="form-label">Mail server pipe</label>
            <input type="text" class="form-control" readonly value="php {{ base_path('artisan') }} tickets:ingest-mail" onclick="this.select()">
            <small class="form-hint">Point a Postfix/Exim alias at this command so inbound mail is read from STDIN.</small>
        </div>
        <div class="d-flex justify-content-between">
            <button type="button" class="btn" wire:click="rotateWebhookToken" wire:confirm="Rotate the webhook token? Existing webhook URLs will stop working.">Rotate webhook token</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div>
