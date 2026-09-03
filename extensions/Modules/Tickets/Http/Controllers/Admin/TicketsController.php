<?php

namespace Extensions\Modules\Tickets\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Extensions\Modules\Tickets\Models\Ticket;

class TicketsController extends Controller
{
    public function index()
    {
        return admin_view('tickets::tickets.index');
    }

    public function create()
    {
        return admin_view('tickets::tickets.create');
    }

    public function view(Ticket $ticket)
    {
        return admin_view('tickets::tickets.view', [
            'ticket' => $ticket,
        ]);
    }
}
