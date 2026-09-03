<?php

use Extensions\Modules\Tickets\Models\Ticket;
use Extensions\Modules\Tickets\Models\TicketMember;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component
{
    #[Locked]
    public int $ticketId;

    #[Locked]
    public ?string $guestToken = null;

    #[Locked]
    public ?string $memberToken = null;

    public string $body = '';

    public string $invite_email = '';

    public bool $showPreview = false;

    public bool $showInvite = false;

    public function mount(int $ticketId, ?string $guestToken = null, ?string $memberToken = null): void
    {
        $this->ticketId = $ticketId;
        $this->guestToken = $guestToken;
        $this->memberToken = $memberToken;

        $ticket = $this->ticket;

        abort_unless($ticket->canBeViewedBy(auth()->user(), $this->accessToken()), 404);
    }

    #[Computed]
    public function ticket(): Ticket
    {
        return Ticket::query()
            ->with(['department', 'user', 'order.package', 'order.payments', 'members.user', 'assignee'])
            ->findOrFail($this->ticketId);
    }

    #[Computed]
    public function timeline()
    {
        return $this->ticket->timeline(auth()->user());
    }

    #[Computed]
    public function member(): ?TicketMember
    {
        if (auth()->check()) {
            return $this->ticket->memberForUser(auth()->user());
        }

        if ($this->memberToken) {
            $fromMemberToken = $this->ticket->members->first(
                fn (TicketMember $member) => hash_equals($member->access_token, $this->memberToken)
            );

            if ($fromMemberToken) {
                return $fromMemberToken;
            }
        }

        if ($this->guestToken) {
            $fromAccessToken = $this->ticket->members->first(
                fn (TicketMember $member) => hash_equals($member->access_token, $this->guestToken)
            );

            if ($fromAccessToken) {
                return $fromAccessToken;
            }

            if (hash_equals($this->ticket->token, $this->guestToken)) {
                return $this->ticket->members->firstWhere('role', TicketMember::ROLE_OWNER);
            }
        }

        return null;
    }

    public function togglePreview(): void
    {
        $this->showPreview = ! $this->showPreview;
    }

    public function reply(bool $close = false): void
    {
        Ticket::actions()->replyAsParticipant([
            'ticket_id' => $this->ticketId,
            'user_id' => auth()->id(),
            'guest_email' => $this->member?->email,
            'access_token' => $this->accessToken(),
            'body' => $this->body,
            'close' => $close,
        ]);

        $this->reset('body', 'showPreview');
        unset($this->ticket, $this->timeline, $this->member);
    }

    public function closeTicket(): void
    {
        Ticket::actions()->close([
            'ticket_id' => $this->ticketId,
            'user_id' => auth()->id(),
            'guest_email' => $this->member?->email,
            'access_token' => $this->accessToken(),
        ]);

        unset($this->ticket, $this->timeline);
    }

    public function reopenTicket(): void
    {
        Ticket::actions()->reopen([
            'ticket_id' => $this->ticketId,
            'user_id' => auth()->id(),
            'guest_email' => $this->member?->email,
            'access_token' => $this->accessToken(),
        ]);

        unset($this->ticket, $this->timeline);
    }

    public function toggleSubscription(): void
    {
        $method = $this->member?->is_subscribed ? 'unsubscribe' : 'subscribe';

        Ticket::actions()->{$method}([
            'ticket_id' => $this->ticketId,
            'user_id' => auth()->id(),
            'guest_email' => $this->member?->email,
            'access_token' => $this->accessToken(),
        ]);

        unset($this->ticket, $this->member, $this->timeline);
    }

    public function invite(): void
    {
        Ticket::actions()->invite([
            'ticket_id' => $this->ticketId,
            'user_id' => auth()->id(),
            'guest_email' => $this->member?->email,
            'access_token' => $this->accessToken(),
            'email' => $this->invite_email,
        ]);

        $this->reset('invite_email', 'showInvite');
        unset($this->ticket, $this->timeline);
    }

    protected function accessToken(): ?string
    {
        return $this->memberToken ?: $this->guestToken;
    }
}

?>

@php
    $ticket = $this->ticket;
    $member = $this->member;
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $ticket->title }}</h1>
                <span class="text-xl font-normal text-gray-400">{{ $ticket->displayNumber() }}</span>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                @if($ticket->isClosed())
                    <span class="inline-flex items-center rounded-full bg-red-600 px-3 py-1 text-xs font-semibold text-white">Closed</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-green-600 px-3 py-1 text-xs font-semibold text-white">Open</span>
                @endif
                @if($ticket->isLocked())
                    <span class="inline-flex items-center rounded-full bg-gray-700 px-3 py-1 text-xs font-semibold text-white">{{ __('tickets::messages.locked') }}</span>
                @endif
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $ticket->requesterName() }} opened this ticket {{ $ticket->created_at->diffForHumans() }} in {{ $ticket->department->name }}
                </span>
            </div>
        </div>
        <a href="{{ auth()->check() ? route('tickets.index') : route('tickets.create') }}" wire:navigate class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">All tickets</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="relative space-y-0">
                <div class="absolute bottom-0 left-5 top-0 w-px bg-gray-200 dark:bg-gray-700"></div>

                @foreach($this->timeline as $item)
                    <div class="relative pb-6 pl-12" wire:key="event-{{ $item->id }}">
                        @if($item->isEvent())
                            <div class="absolute left-3.5 top-1 flex h-4 w-4 items-center justify-center rounded-full border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $item->eventSummary() }}
                                <span class="text-gray-400">· {{ $item->created_at->diffForHumans() }}</span>
                            </p>
                        @else
                            <div class="absolute left-2 top-2 h-6 w-6 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                @if($item->user)
                                    <img src="{{ $item->user->getAvatarUrl() }}" alt="" class="h-6 w-6">
                                @else
                                    <span class="flex h-6 w-6 items-center justify-center text-xs font-semibold text-gray-600 dark:text-gray-200">{{ strtoupper(substr($item->authorDisplayName(), 0, 1)) }}</span>
                                @endif
                            </div>
                            <article class="overflow-hidden rounded-lg border {{ $item->isFromAdmin() ? 'border-primary-200 dark:border-primary-800' : 'border-gray-200 dark:border-gray-700' }} bg-white dark:bg-gray-800">
                                <header class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 bg-gray-50 px-4 py-2 dark:border-gray-700 dark:bg-gray-900">
                                    <div class="flex items-center gap-2 text-sm">
                                        <span class="font-semibold text-gray-900 dark:text-white">{{ $item->authorDisplayName() }}</span>
                                        @if($item->isFromAdmin())
                                            <span class="rounded bg-primary-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary-800 dark:bg-primary-900 dark:text-primary-200">{{ __('tickets::messages.support') }}</span>
                                        @endif
                                        <span class="text-gray-400">commented {{ $item->created_at->diffForHumans() }}</span>
                                    </div>
                                </header>
                                <div class="prose dark:prose-invert max-w-none px-4 py-3 text-sm text-gray-800 dark:text-gray-100">
                                    {!! $item->renderedBody() !!}
                                </div>
                            </article>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($ticket->isLocked())
                <div class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('tickets::messages.ticket_locked') }}</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ __('tickets::messages.ticket_locked_desc') }}</p>
                </div>
            @elseif($ticket->isOpen())
                <div class="mt-2 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <x-tickets::markdown-composer
                        id="ticket-reply-body"
                        wire:model="body"
                        placeholder="Leave a comment. Markdown is supported."
                        :showPreview="$showPreview"
                        :previewHtml="\Extensions\Modules\Tickets\Models\Ticket::renderMarkdown($body)"
                        :rows="8"
                    />
                    @error('body') <x-theme::form.error class="mt-2" :text="$message"/> @enderror
                    <div class="mt-3 flex flex-wrap items-center justify-end gap-2">
                        <button type="button" wire:click="reply(true)" wire:confirm="Close this ticket after commenting?" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            Close and comment
                        </button>
                        <x-theme::button.primary type="button" wire:click="reply(false)" wire:loading.attr="disabled">
                            Comment
                        </x-theme::button.primary>
                    </div>
                </div>
            @else
                <div class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">This ticket is closed. Reopen it to continue the conversation.</p>
                    <x-theme::button.primary type="button" wire:click="reopenTicket" text="Reopen ticket"/>
                </div>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="font-medium text-gray-500 dark:text-gray-400">Department</dt>
                        <dd class="mt-1 text-gray-900 dark:text-white">{{ $ticket->department->name }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500 dark:text-gray-400">Priority</dt>
                        <dd class="mt-1 capitalize text-gray-900 dark:text-white">{{ $ticket->priority }}</dd>
                    </div>
                </dl>
            </div>

            @include('tickets::client_area.default.tickets.partials.service-context', ['ticket' => $ticket])

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Notifications</h3>
                @if($member)
                    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                        {{ $member->is_subscribed ? 'You are subscribed to email updates.' : 'You are not receiving email updates.' }}
                    </p>
                    <button type="button" wire:click="toggleSubscription" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        {{ $member->is_subscribed ? 'Unsubscribe' : 'Subscribe' }}
                    </button>
                @endif
                @if($ticket->isOpen() && ! $ticket->isLocked())
                    <button type="button" wire:click="closeTicket" wire:confirm="Close this ticket?" class="mt-2 w-full rounded-lg border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950">
                        Close ticket
                    </button>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">People</h3>
                    @if($ticket->department->allow_invites && ! $ticket->isLocked())
                        <button type="button" wire:click="$toggle('showInvite')" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">Invite</button>
                    @endif
                </div>
                <ul class="space-y-2">
                    @foreach($ticket->members as $person)
                        <li class="flex items-center justify-between text-sm">
                            <span class="text-gray-900 dark:text-white">{{ $person->displayName() }}</span>
                            <span class="text-xs capitalize text-gray-400">{{ $person->role }}</span>
                        </li>
                    @endforeach
                </ul>
                @if($showInvite)
                    <div class="mt-3 space-y-2">
                        <x-theme::form.input type="email" wire:model="invite_email" placeholder="email@example.com"/>
                        @error('email') <x-theme::form.error :text="$message"/> @enderror
                        <x-theme::button.primary type="button" class="w-full" wire:click="invite" text="Send invite"/>
                    </div>
                @endif
            </div>
        </aside>
    </div>
</div>
