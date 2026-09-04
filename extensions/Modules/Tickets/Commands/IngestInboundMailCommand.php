<?php

namespace Extensions\Modules\Tickets\Commands;

use Extensions\Modules\Tickets\Models\Ticket;
use Illuminate\Console\Command;

class IngestInboundMailCommand extends Command
{
    protected $signature = 'tickets:ingest-mail {file? : Path to a raw RFC822 message. Reads STDIN when omitted.}';

    protected $description = 'Add a ticket comment from a raw email reply piped in by the mail server.';

    public function handle(): int
    {
        $path = $this->argument('file');
        $raw = is_string($path) && $path !== ''
            ? file_get_contents($path)
            : stream_get_contents(STDIN);

        if ($raw === false || trim($raw) === '') {
            $this->error('No email content was provided.');

            return self::FAILURE;
        }

        $message = Ticket::actions()->replyFromInboundMail($raw);

        if ($message === null) {
            $this->warn('Email was ignored (unknown ticket, unknown sender, duplicate, or empty).');

            return self::SUCCESS;
        }

        $this->info('Added reply to ticket #'.$message->ticket->number.'.');

        return self::SUCCESS;
    }
}
