<?php

namespace App\Console\Commands;

use App\Models\ApprovalRequest;
use App\Models\Company;
use App\Notifications\ApprovalNotification;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class MonitorApprovalSla extends Command
{
    protected $signature = 'approvals:monitor-sla';

    protected $description = 'Mengirim peringatan SLA untuk approval yang melewati batas waktu (sekali per dokumen)';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $notified = 0;
        Company::query()->select('id')->chunkById(100, function ($companies) use ($dispatcher, &$notified) {
            foreach ($companies as $company) {
                ApprovalRequest::with(['workflow.steps'])
                    ->where('company_id', $company->id)
                    ->where('status', 'pending')
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now())
                    ->chunkById(100, function ($requests) use ($dispatcher, &$notified) {
                        foreach ($requests as $request) {
                            $alreadyNotified = DatabaseNotification::query()
                                ->where('type', ApprovalNotification::class)
                                ->where('data->event', 'approval_sla_overdue')
                                ->where('data->request_id', $request->id)
                                ->exists();
                            if ($alreadyNotified) {
                                continue;
                            }
                            $dispatcher->slaOverdue($request);
                            $notified++;
                        }
                    });
            }
        });
        $this->info("Peringatan SLA terkirim untuk {$notified} approval.");

        return self::SUCCESS;
    }
}
