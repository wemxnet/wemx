@if(isset($user) && auth()->user()?->hasPermission('admin.tickets.view'))
    @php
        $userTickets = \Extensions\Modules\Tickets\Models\Ticket::openedByUser($user, 20);
        $userTicketCount = \Extensions\Modules\Tickets\Models\Ticket::query()->openedBy($user)->count();
    @endphp
    <div class="mb-3">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Tickets
                    @if($userTicketCount > 0)
                        <span class="badge bg-blue-lt ms-2">{{ $userTicketCount }}</span>
                    @endif
                </h3>
                <div class="card-actions">
                    @perm('admin.tickets.create')
                        <a href="{{ route('admin.tickets.create', ['user' => $user->id]) }}" wire:navigate class="btn btn-sm">Open ticket</a>
                    @endperm
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table table-hover">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Last reply</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($userTickets as $ticket)
                            <tr>
                                <td>
                                    <a href="{{ $ticket->adminUrl() }}" wire:navigate class="text-reset">
                                        <div class="font-weight-medium">{{ $ticket->title }}</div>
                                        <div class="text-secondary">{{ $ticket->displayNumber() }}{{ $ticket->isLocked() ? ' · Locked' : '' }}</div>
                                    </a>
                                </td>
                                <td>{{ $ticket->department?->name }}</td>
                                <td>
                                    @if($ticket->isClosed())
                                        <span class="badge bg-secondary-lt">Closed</span>
                                    @elseif($ticket->awaitingStaff())
                                        <span class="badge bg-red-lt">Needs reply</span>
                                    @else
                                        <span class="badge bg-green-lt">Answered</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $ticket->priorityBadgeClass() }}">{{ ucfirst($ticket->priority) }}</span>
                                </td>
                                <td class="text-secondary">
                                    {{ $ticket->last_replied_at?->diffForHumans() }}
                                </td>
                                <td class="text-end">
                                    <a href="{{ $ticket->adminUrl() }}" wire:navigate>View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-4">This user has not opened any tickets.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($userTicketCount > $userTickets->count())
                <div class="card-footer text-secondary">
                    Showing the {{ $userTickets->count() }} most important of {{ $userTicketCount }} tickets.
                </div>
            @endif
        </div>
    </div>
@endif
