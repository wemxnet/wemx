<?php

namespace Extensions\Modules\Tickets\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Extensions\Modules\Tickets\Models\Ticket;
use Extensions\Modules\Tickets\Models\TicketDepartment;
use Illuminate\Http\Request;

class TicketsController extends Controller
{
    public function index()
    {
        if (! auth()->check()) {
            if (TicketDepartment::acceptsGuests()->exists()) {
                return redirect()->route('tickets.create');
            }

            return redirect()->route('login');
        }

        return client_view('tickets::tickets.index');
    }

    public function create()
    {
        if (! auth()->check() && ! TicketDepartment::acceptsGuests()->exists()) {
            return redirect()->route('login');
        }

        return client_view('tickets::tickets.create');
    }

    public function view(Ticket $ticket)
    {
        abort_unless($ticket->canBeViewedBy(auth()->user()), 404);

        return client_view('tickets::tickets.view', [
            'ticket' => $ticket,
        ]);
    }

    public function guest(Request $request, string $token)
    {
        $ticket = Ticket::query()->with('members')->where('token', $token)->first();

        if (! $ticket) {
            $ticket = Ticket::query()
                ->whereHas('members', fn ($query) => $query->where('access_token', $token))
                ->first();
        }

        abort_unless($ticket && $ticket->canBeViewedBy(auth()->user(), $token), 404);

        if (auth()->check() && $ticket->isParticipant(auth()->user())) {
            return redirect()->route('tickets.view', $ticket);
        }

        return client_view('tickets::tickets.view', [
            'ticket' => $ticket,
            'guestToken' => $token,
            'memberToken' => $request->query('member'),
        ]);
    }
}
