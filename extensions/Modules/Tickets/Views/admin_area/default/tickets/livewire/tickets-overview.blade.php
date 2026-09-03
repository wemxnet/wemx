<?php

use Extensions\Modules\Tickets\Models\Ticket;
use Extensions\Modules\Tickets\Models\TicketDepartment;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $queue = 'needs_reply';

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $department_id = null;

    #[Url]
    public ?string $priority = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingQueue(): void
    {
        $this->resetPage();
    }
}

?>

@php
    $base = Ticket::query()->with(['department', 'user', 'assignee']);

    $needsReplyCount = (clone $base)->awaitingStaff()->count();
    $openCount = (clone $base)->open()->count();
    $urgentCount = (clone $base)->open()->where('priority', Ticket::PRIORITY_URGENT)->count();
    $answeredCount = (clone $base)->open()->where('last_reply_from', Ticket::REPLY_STAFF)->count();

    $query = Ticket::query()->with(['department', 'user', 'assignee'])->orderedForStaff();

    $query = match ($this->queue) {
        'needs_reply' => $query->awaitingStaff(),
        'answered' => $query->open()->where('last_reply_from', Ticket::REPLY_STAFF),
        'open' => $query->open(),
        'closed' => $query->closed(),
        'urgent' => $query->open()->where('priority', Ticket::PRIORITY_URGENT),
        default => $query,
    };

    if ($this->department_id) {
        $query->where('department_id', $this->department_id);
    }

    if ($this->priority) {
        $query->where('priority', $this->priority);
    }

    if ($this->search !== '') {
        $query->search($this->search);
    }

    $tickets = $query->paginate(20);
    $departments = TicketDepartment::query()->ordered()->get();
@endphp

<div>
    <div class="row row-deck row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <a href="#" wire:click.prevent="$set('queue', 'needs_reply')" class="card card-link {{ $this->queue === 'needs_reply' ? 'border-primary' : '' }}">
                <div class="card-body">
                    <div class="subheader">Needs reply</div>
                    <div class="h1 mb-0">{{ $needsReplyCount }}</div>
                    <div class="text-secondary">Waiting on staff, highest first</div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-3">
            <a href="#" wire:click.prevent="$set('queue', 'urgent')" class="card card-link {{ $this->queue === 'urgent' ? 'border-danger' : '' }}">
                <div class="card-body">
                    <div class="subheader">Urgent</div>
                    <div class="h1 mb-0 text-danger">{{ $urgentCount }}</div>
                    <div class="text-secondary">Open urgent tickets</div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-3">
            <a href="#" wire:click.prevent="$set('queue', 'answered')" class="card card-link {{ $this->queue === 'answered' ? 'border-success' : '' }}">
                <div class="card-body">
                    <div class="subheader">Answered</div>
                    <div class="h1 mb-0">{{ $answeredCount }}</div>
                    <div class="text-secondary">Waiting on the customer</div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-3">
            <a href="#" wire:click.prevent="$set('queue', 'open')" class="card card-link {{ $this->queue === 'open' ? 'border-primary' : '' }}">
                <div class="card-body">
                    <div class="subheader">Open</div>
                    <div class="h1 mb-0">{{ $openCount }}</div>
                    <div class="text-secondary">All unresolved tickets</div>
                </div>
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <ul class="nav nav-pills card-header-pills">
                @foreach(['needs_reply' => 'Needs reply', 'urgent' => 'Urgent', 'answered' => 'Answered', 'open' => 'Open', 'closed' => 'Closed', 'all' => 'All'] as $key => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ $this->queue === $key ? 'active' : '' }}" href="#" wire:click.prevent="$set('queue', '{{ $key }}')">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="card-body border-bottom py-3">
            <div class="row g-2">
                <div class="col-md-4">
                    <input type="search" class="form-control" placeholder="Search subject, number, email…" wire:model.live.debounce.300ms="search">
                </div>
                <div class="col-md-4">
                    <select class="form-select" wire:model.live="department_id">
                        <option value="">All departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select" wire:model.live="priority">
                        <option value="">All priorities</option>
                        @foreach(\Extensions\Modules\Tickets\Models\Ticket::priorities() as $priority)
                            <option value="{{ $priority }}">{{ ucfirst($priority) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Customer</th>
                        <th>Department</th>
                        <th>Priority</th>
                        <th>Waiting</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                        <tr wire:key="admin-ticket-{{ $ticket->id }}">
                            <td>
                                <a href="{{ route('admin.tickets.view', $ticket) }}" wire:navigate class="text-reset">
                                    <div class="font-weight-medium">{{ $ticket->title }}</div>
                                    <div class="text-secondary">{{ $ticket->displayNumber() }} · {{ $ticket->isClosed() ? 'Closed' : ($ticket->awaitingStaff() ? 'Needs reply' : 'Answered') }}{{ $ticket->isLocked() ? ' · Locked' : '' }}</div>
                                </a>
                            </td>
                            <td>
                                <div>{{ $ticket->requesterName() }}</div>
                                <div class="text-secondary">{{ $ticket->requesterEmail() }}</div>
                            </td>
                            <td>{{ $ticket->department->name }}</td>
                            <td>
                                <span class="badge {{ $ticket->priorityBadgeClass() }}">{{ ucfirst($ticket->priority) }}</span>
                            </td>
                            <td class="text-secondary">
                                {{ $ticket->last_replied_at?->diffForHumans() }}
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.tickets.view', $ticket) }}" wire:navigate>Respond</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-4">No tickets in this queue.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tickets->hasPages())
            <div class="card-footer">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>
</div>
