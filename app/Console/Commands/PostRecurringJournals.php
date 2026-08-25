<?php

namespace App\Console\Commands;

use App\Services\RecurringJournalService;
use Illuminate\Console\Command;

class PostRecurringJournals extends Command
{
    protected $signature = 'journals:post-recurring';

    protected $description = 'Posting jurnal berulang yang jatuh tempo hari ini (idempotent per periode)';

    public function handle(RecurringJournalService $service): int
    {
        $posted = $service->postDue();
        $this->info("Recurring journals posted: {$posted}");

        return self::SUCCESS;
    }
}
