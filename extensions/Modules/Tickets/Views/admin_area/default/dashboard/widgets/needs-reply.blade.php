@if(auth()->user()?->hasPermission('admin.tickets'))
    @php
        $ticketsNeedingReply = \Extensions\Modules\Tickets\Models\Ticket::needingStaffReply(8);
        $ticketsNeedingReplyCount = \Extensions\Modules\Tickets\Models\Ticket::query()->awaitingStaff()->count();
    @endphp
    <div class="mb-3">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Tickets needing reply
                    @if($ticketsNeedingReplyCount > 0)
                        <span class="badge bg-red-lt ms-2">{{ $ticketsNeedingReplyCount }}</span>
                    @endif
                </h3>
                <div class="card-actions">
                    <a href="{{ route('admin.tickets.index') }}" wire:navigate class="btn btn-sm">View inbox</a>
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
                        @forelse($ticketsNeedingReply as $ticket)
                            <tr>
                                <td>
                                    <a href="{{ $ticket->adminUrl() }}" wire:navigate class="text-reset">
                                        <div class="font-weight-medium">{{ $ticket->title }}</div>
                                        <div class="text-secondary">{{ $ticket->displayNumber() }}{{ $ticket->isLocked() ? ' · Locked' : '' }}</div>
                                    </a>
                                </td>
                                <td>
                                    @if($ticket->user)
                                        <a href="{{ route('admin.users.edit', $ticket->user) }}" wire:navigate class="text-reset">{{ $ticket->requesterName() }}</a>
                                        <div class="text-secondary">{{ $ticket->requesterEmail() }}</div>
                                    @else
                                        <div>{{ $ticket->requesterName() }}</div>
                                        <div class="text-secondary">{{ $ticket->requesterEmail() }}</div>
                                    @endif
                                </td>
                                <td>{{ $ticket->department?->name }}</td>
                                <td>
                                    <span class="badge {{ $ticket->priorityBadgeClass() }}">{{ ucfirst($ticket->priority) }}</span>
                                </td>
                                <td class="text-secondary">
                                    {{ $ticket->last_replied_at?->diffForHumans() }}
                                </td>
                                <td class="text-end">
                                    <a href="{{ $ticket->adminUrl() }}" wire:navigate>Respond</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-4">No tickets are waiting on staff.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($ticketsNeedingReplyCount > $ticketsNeedingReply->count())
                <div class="card-footer text-secondary">
                    Showing the {{ $ticketsNeedingReply->count() }} most important of {{ $ticketsNeedingReplyCount }} tickets waiting on staff.
                </div>
            @endif
        </div>
    </div>
@endif
