<?php

use Extensions\Modules\Tickets\Models\Ticket;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'open';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }
}

?>

@php
    $query = Ticket::query()
        ->with(['department', 'user'])
        ->forUser(auth()->user())
        ->latest('last_replied_at');

    if ($this->status === 'open') {
        $query->open();
    } elseif ($this->status === 'closed') {
        $query->closed();
    }

    if ($this->search !== '') {
        $query->search($this->search);
    }

    $tickets = $query->paginate(15);
    $openCount = Ticket::query()->forUser(auth()->user())->open()->count();
@endphp

<section>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Tickets</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $openCount }} open {{ Str::plural('ticket', $openCount) }}</p>
        </div>
        <a href="{{ route('tickets.create') }}" wire:navigate class="inline-flex items-center justify-center rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700">
            New ticket
        </a>
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-col gap-3 border-b border-gray-200 p-4 dark:border-gray-700 md:flex-row md:items-center md:justify-between">
            <div class="flex gap-2">
                @foreach(['open' => 'Open', 'closed' => 'Closed', 'all' => 'All'] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('status', '{{ $value }}')"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $this->status === $value ? 'bg-primary-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600' }}"
                    >{{ $label }}</button>
                @endforeach
            </div>
            <div class="relative w-full md:max-w-xs">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search tickets" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            </div>
        </div>

        @if($tickets->isEmpty())
            <div class="p-6">
                <x-theme::empty-state
                    title="No tickets found"
                    description="Open a ticket when you need help with an order, billing, or a technical issue."
                    action-text="New ticket"
                    :action-href="route('tickets.create')"
                    :action-navigate="true"
                />
            </div>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($tickets as $ticket)
                    <li class="p-4 hover:bg-gray-50 dark:hover:bg-gray-900/40" wire:key="ticket-{{ $ticket->id }}">
                        <a href="{{ route('tickets.view', $ticket) }}" wire:navigate class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $ticket->title }}</span>
                                    <span class="text-sm text-gray-400">{{ $ticket->displayNumber() }}</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $ticket->department->name }} · updated {{ $ticket->last_replied_at?->diffForHumans() }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if($ticket->isLocked())
                                    <span class="rounded-sm bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-300">Locked</span>
                                @endif
                                @if($ticket->isClosed())
                                    <x-theme::badge.danger text="Closed"/>
                                @elseif($ticket->awaitingStaff())
                                    <x-theme::badge.warning text="Awaiting reply"/>
                                @else
                                    <x-theme::badge.success text="Answered"/>
                                @endif
                                <span class="rounded-sm px-2.5 py-0.5 text-xs font-medium
                                    {{ $ticket->priority === 'urgent' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' : '' }}
                                    {{ $ticket->priority === 'high' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300' : '' }}
                                    {{ $ticket->priority === 'medium' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' : '' }}
                                    {{ $ticket->priority === 'low' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}
                                ">{{ ucfirst($ticket->priority) }}</span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-gray-200 p-4 dark:border-gray-700">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>
</section>
