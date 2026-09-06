<?php

namespace App\Console\Commands\Cronjobs;

use App\Models\MassMail;
use Illuminate\Console\Command;

class SendMassMailsCommand extends Command
{
    protected $signature = 'cronjobs:mass-mails:send {--chunk=100 : Recipients to process per campaign this run}';

    protected $description = 'Send queued mass mail campaigns in the background';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $processed = MassMail::actions()->processDue($chunkSize);

        $this->info("Processed {$processed} mass mail recipient(s).");

        return self::SUCCESS;
    }
}
