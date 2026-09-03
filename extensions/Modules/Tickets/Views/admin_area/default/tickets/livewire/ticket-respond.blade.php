<?php

use App\Models\Order;
use App\Models\User;
use Extensions\Modules\Tickets\Models\Ticket;
use Extensions\Modules\Tickets\Models\TicketDepartment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component
{
    #[Locked]
    public int $ticketId;

    public string $body = '';

    public string $note = '';

    public string $invite_email = '';

    public string $priority;

    public int $department_id;

    public ?int $assigned_to = null;

    public ?int $order_id = null;

    public function mount(int $ticketId): void
    {
        $this->ticketId = $ticketId;
        $ticket = $this->ticket;
        $this->priority = $ticket->priority;
        $this->department_id = $ticket->department_id;
        $this->assigned_to = $ticket->assigned_to;
        $this->order_id = $ticket->order_id;
    }

    #[Computed]
    public function ticket(): Ticket
    {
        return Ticket::query()
            ->with([
                'department',
                'user',
                'order.package.serverConnection',
                'order.payments',
                'members.user',
                'assignee',
                'messages.user',
            ])
            ->findOrFail($this->ticketId);
    }

    #[Computed]
    public function departments()
    {
        return TicketDepartment::query()
            ->ordered()
            ->where(function ($query) {
                $query->active()->orWhere('id', $this->department_id);
            })
            ->get();
    }

    #[Computed]
    public function staffUsers()
    {
        return User::query()->where('id', 1)->orWhereHas('roles')->orderBy('username')->limit(50)->get();
    }

    #[Computed]
    public function customerOrders()
    {
        if (! $this->ticket->user_id) {
            return collect();
        }

        $orders = Order::query()
            ->with('package')
            ->where('user_id', $this->ticket->user_id)
            ->latest()
            ->limit(50)
            ->get();

        if ($this->order_id && ! $orders->contains('id', $this->order_id)) {
            $current = Order::query()->with('package')->find($this->order_id);

            if ($current) {
                $orders->prepend($current);
            }
        }

        return $orders;
    }

    public function reply(bool $close = false): void
    {
        Ticket::actions()->replyAsAdmin([
            'ticket_id' => $this->ticketId,
            'admin_user_id' => auth()->id(),
            'body' => $this->body,
            'close' => $close,
        ]);

        $this->reset('body');
        unset($this->ticket);
    }

    public function addNote(): void
    {
        Ticket::actions()->addInternalNote([
            'ticket_id' => $this->ticketId,
            'admin_user_id' => auth()->id(),
            'body' => $this->note,
        ]);

        $this->reset('note');
        unset($this->ticket);
    }

    public function saveDetails(): void
    {
        Ticket::actions()->changePriority([
            'ticket_id' => $this->ticketId,
            'admin_user_id' => auth()->id(),
            'priority' => $this->priority,
        ]);

        Ticket::actions()->changeDepartment([
            'ticket_id' => $this->ticketId,
            'admin_user_id' => auth()->id(),
            'department_id' => $this->department_id,
        ]);

        Ticket::actions()->assign([
            'ticket_id' => $this->ticketId,
            'admin_user_id' => auth()->id(),
            'assigned_to' => $this->assigned_to ?: null,
        ]);

        Ticket::actions()->linkOrder([
            'ticket_id' => $this->ticketId,
            'admin_user_id' => auth()->id(),
            'order_id' => $this->order_id ?: null,
        ]);

        unset($this->ticket);
        $this->dispatch('alert', type: 'success', message: 'Ticket updated.');
    }

    public function closeTicket(): void
    {
        Ticket::actions()->close([
            'ticket_id' => $this->ticketId,
            'user_id' => auth()->id(),
            'as_admin' => true,
        ]);

        unset($this->ticket);
    }

    public function reopenTicket(): void
    {
        Ticket::actions()->reopen([
            'ticket_id' => $this->ticketId,
            'user_id' => auth()->id(),
            'as_admin' => true,
        ]);

        unset($this->ticket);
    }

    public function lockTicket(): void
    {
        Ticket::actions()->lock([
            'ticket_id' => $this->ticketId,
            'admin_user_id' => auth()->id(),
        ]);

        unset($this->ticket);
        $this->dispatch('alert', type: 'success', message: 'Ticket locked. Clients can no longer reply.');
    }

    public function unlockTicket(): void
    {
        Ticket::actions()->unlock([
            'ticket_id' => $this->ticketId,
            'admin_user_id' => auth()->id(),
        ]);

        unset($this->ticket);
        $this->dispatch('alert', type: 'success', message: 'Ticket unlocked.');
    }

    public function invite(): void
    {
        Ticket::actions()->invite([
            'ticket_id' => $this->ticketId,
            'user_id' => auth()->id(),
            'as_admin' => true,
            'email' => $this->invite_email,
        ]);

        $this->reset('invite_email');
        unset($this->ticket);
    }

    public function removeMember(int $memberId): void
    {
        Ticket::actions()->removeMember([
            'ticket_id' => $this->ticketId,
            'admin_user_id' => auth()->id(),
            'member_id' => $memberId,
        ]);

        unset($this->ticket);
    }
}

?>

@php
    $ticket = $this->ticket;
@endphp

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-stamp">
                <div class="card-stamp-icon {{ $ticket->isClosed() ? 'bg-red' : ($ticket->isLocked() ? 'bg-secondary' : 'bg-green') }}">
                    <x-admin::icon :icon="$ticket->isLocked() || $ticket->isClosed() ? 'lock' : 'message-circle'" outline class="icon"/>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    @if($ticket->isClosed())
                        <span class="badge bg-red-lt me-2">Closed</span>
                    @elseif($ticket->awaitingStaff())
                        <span class="badge bg-yellow-lt me-2">Needs reply</span>
                    @else
                        <span class="badge bg-green-lt me-2">Answered</span>
                    @endif
                    @if($ticket->isLocked())
                        <span class="badge bg-secondary-lt me-2">{{ __('tickets::messages.locked') }}</span>
                    @endif
                    <span class="badge {{ $ticket->priorityBadgeClass() }}">{{ ucfirst($ticket->priority) }}</span>
                </div>
                <h2 class="mb-1">{{ $ticket->title }} <span class="text-secondary">{{ $ticket->displayNumber() }}</span></h2>
                <div class="text-secondary">
                    Opened {{ $ticket->created_at->diffForHumans() }} by {{ $ticket->requesterName() }}
                    ({{ $ticket->requesterEmail() }}) in {{ $ticket->department->name }}
                </div>
            </div>
        </div>

        <ul class="timeline">
            @foreach($ticket->messages as $item)
                <li class="timeline-event" wire:key="admin-event-{{ $item->id }}">
                    <div class="timeline-event-icon {{ $item->isEvent() ? 'bg-secondary-lt' : ($item->isNote() ? 'bg-yellow-lt' : ($item->isFromAdmin() ? 'bg-primary-lt' : 'bg-azure-lt')) }}">
                        <x-admin::icon :icon="$item->timelineIcon()" outline class="icon icon-1"/>
                    </div>
                    <div class="card timeline-event-card">
                        @if($item->isEvent())
                            <div class="card-body py-2">
                                <span class="text-secondary">{{ $item->eventSummary() }} · {{ $item->created_at->diffForHumans() }}</span>
                            </div>
                        @else
                            <div class="card-header">
                                <div>
                                    <strong>{{ $item->authorDisplayName() }}</strong>
                                    @if($item->isNote())
                                        <span class="badge bg-yellow-lt">{{ __('tickets::messages.internal_note') }}</span>
                                    @elseif($item->isFromAdmin())
                                        <span class="badge bg-primary-lt">{{ __('tickets::messages.staff') }}</span>
                                    @endif
                                    <span class="text-secondary ms-2">{{ $item->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                            <div class="card-body markdown">
                                {!! $item->renderedBody() !!}
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        @if($ticket->isOpen())
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Reply</h3>
                </div>
                <div class="card-body">
                    <x-admin::form.markdown-editor id="admin-reply-body" wire:model="body" :rows="8"/>
                    @error('body') <x-admin::form.error :message="$message"/> @enderror
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <button type="button" class="btn" wire:click="reply(true)" wire:confirm="Close this ticket after sending the reply?">Close and reply</button>
                    <button type="button" class="btn btn-primary" wire:click="reply(false)">Reply</button>
                </div>
            </div>
        @else
            <div class="card mb-3">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <span>This ticket is closed.</span>
                    <button type="button" class="btn btn-primary" wire:click="reopenTicket">Reopen</button>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Internal note</h3>
            </div>
            <div class="card-body">
                <textarea class="form-control" rows="4" wire:model="note" placeholder="Visible only to staff"></textarea>
                @error('note') <x-admin::form.error :message="$message"/> @enderror
            </div>
            <div class="card-footer text-end">
                <button type="button" class="btn" wire:click="addNote">Add note</button>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        @include('tickets::admin_area.default.tickets.partials.service-context', ['ticket' => $ticket])

        <form class="card mb-3" wire:submit="saveDetails">
            <div class="card-header">
                <h3 class="card-title">Details</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Department</label>
                    <select class="form-select" wire:model="department_id">
                        @foreach($this->departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}{{ $department->is_active ? '' : ' (Inactive)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Priority</label>
                    <select class="form-select" wire:model="priority">
                        @foreach(\Extensions\Modules\Tickets\Models\Ticket::priorities() as $priority)
                            <option value="{{ $priority }}">{{ ucfirst($priority) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Assigned to</label>
                    <select class="form-select" wire:model="assigned_to">
                        <option value="">Unassigned</option>
                        @foreach($this->staffUsers as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->full_name ?: $staff->username }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Related order</label>
                    @if($this->customerOrders->isNotEmpty())
                        <select class="form-select @error('order_id') is-invalid @enderror" wire:model="order_id">
                            <option value="">None</option>
                            @foreach($this->customerOrders as $order)
                                <option value="{{ $order->id }}">#{{ $order->id }} — {{ $order->package->name ?? 'Order' }} ({{ ucfirst($order->status) }})</option>
                            @endforeach
                        </select>
                    @else
                        <input type="number" class="form-control @error('order_id') is-invalid @enderror" wire:model="order_id" placeholder="Optional order ID">
                    @endif
                    @error('order_id') <x-admin::form.error :message="$message"/> @enderror
                </div>
                @if($ticket->user)
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <div>
                            <a href="{{ route('admin.users.edit', $ticket->user) }}" wire:navigate>{{ $ticket->user->full_name }} ({{ $ticket->user->email }})</a>
                        </div>
                    </div>
                @endif
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Status</h3>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    @if($ticket->isOpen())
                        <button type="button" class="btn btn-danger w-100" wire:click="closeTicket" wire:confirm="Close this ticket?">Close ticket</button>
                    @else
                        <button type="button" class="btn btn-primary w-100" wire:click="reopenTicket">Reopen ticket</button>
                    @endif
                    @if($ticket->isLocked())
                        <button type="button" class="btn w-100" wire:click="unlockTicket">{{ __('tickets::messages.unlock_ticket') }}</button>
                    @else
                        <button type="button" class="btn w-100" wire:click="lockTicket" wire:confirm="Lock this ticket? Clients will not be able to reply until it is unlocked.">{{ __('tickets::messages.lock_ticket') }}</button>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">People</h3>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-3">
                    @foreach($ticket->members as $person)
                        <li class="d-flex justify-content-between align-items-center mb-2" wire:key="member-{{ $person->id }}">
                            <span>
                                {{ $person->displayName() }}
                                <span class="text-secondary">· {{ $person->role }}</span>
                            </span>
                            @if($person->role !== 'owner')
                                <button type="button" class="btn btn-sm btn-ghost-danger" wire:click="removeMember({{ $person->id }})" wire:confirm="Remove this person from the ticket?">Remove</button>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if($ticket->department->allow_invites)
                    <div class="input-group">
                        <input type="email" class="form-control" wire:model="invite_email" placeholder="Invite by email">
                        <button type="button" class="btn" wire:click="invite">Invite</button>
                    </div>
                    @error('email') <x-admin::form.error :message="$message"/> @enderror
                @endif
            </div>
        </div>
    </div>
</div>
