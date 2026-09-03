<?php

namespace Extensions\Modules\Tickets\Commands;

use Extensions\Modules\Tickets\Models\Ticket;
use Illuminate\Console\Command;

class AutoCloseInactiveTicketsCommand extends Command
{
    protected $signature = 'tickets:auto-close';

    protected $description = 'Close tickets that have been waiting on the customer past the department inactivity period.';

    public function handle(): int
    {
        $closed = Ticket::actions()->autoCloseInactive();

        $this->info('Closed '.$closed.' inactive '.($closed === 1 ? 'ticket' : 'tickets').'.');

        return self::SUCCESS;
    }
}
