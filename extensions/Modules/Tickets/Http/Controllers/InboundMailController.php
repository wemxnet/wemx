<?php

namespace Extensions\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use Extensions\Modules\Tickets\Models\Ticket;
use Extensions\Modules\Tickets\Support\TicketInboundMail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InboundMailController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $token = $request->query('token')
            ?: $request->header('X-Tickets-Inbound-Token')
            ?: $request->bearerToken();

        abort_unless(is_string($token) && TicketInboundMail::tokenIsValid($token), 403);

        $raw = $request->getContent();

        abort_if(trim($raw) === '', 422);

        Ticket::actions()->replyFromInboundMail($raw);

        return response()->noContent();
    }
}
