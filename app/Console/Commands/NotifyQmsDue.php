<?php

namespace App\Console\Commands;

use App\Models\CorrectiveAction;
use App\Models\Nonconformity;
use App\Models\User;
use App\Notifications\OperationalNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class NotifyQmsDue extends Command
{
    protected $signature = 'qms:notify-due';

    protected $description = 'Mengirim notifikasi CAPA/NCR mendekati atau melewati tenggat (sekali per dokumen/hari)';

    public function handle(): int
    {
        $notified = 0;
        $horizon = now()->addDays(7)->toDateString();

        CorrectiveAction::with('nonconformity')->whereIn('status', ['open', 'in_progress'])->whereDate('due_at', '<=', $horizon)
            ->chunkById(100, function ($actions) use (&$notified) {
                foreach ($actions as $action) {
                    $event = $action->due_at->isPast() ? 'ncr_overdue' : 'capa_due';
                    $owner = User::find($action->owner_id);
                    if (! $owner || $this->alreadyNotified($owner->id, $event, $action->id)) {
                        continue;
                    }
                    $owner->notify(new OperationalNotification($event, [
                        'key' => (string) $action->id,
                        'capa_id' => $action->id,
                        'title' => str($action->action)->limit(60),
                        'due_at' => $action->due_at->format('d/m/Y'),
                        'ncr' => $action->nonconformity?->number ?? ('NCR #'.$action->nonconformity_id),
                        'url' => '/admin/qms',
                    ]));
                    $notified++;
                }
            });

        Nonconformity::whereIn('status', ['open', 'containment'])->whereDate('due_at', '<=', $horizon)
            ->chunkById(100, function ($ncrs) use (&$notified) {
                foreach ($ncrs as $ncr) {
                    if ($ncr->due_at === null) {
                        continue;
                    }
                    $event = $ncr->due_at->isPast() ? 'ncr_overdue' : 'capa_due';
                    $owner = User::find($ncr->reported_by);
                    if (! $owner || $this->alreadyNotified($owner->id, $event, 'ncr-'.$ncr->id)) {
                        continue;
                    }
                    $owner->notify(new OperationalNotification($event, [
                        'key' => 'ncr-'.$ncr->id,
                        'ncr_id' => $ncr->id,
                        'title' => str($ncr->description)->limit(60),
                        'due_at' => $ncr->due_at?->format('d/m/Y'),
                        'ncr' => $ncr->number,
                        'url' => '/admin/qms',
                    ]));
                    $notified++;
                }
            });

        $this->info("Notifikasi QMS terkirim: {$notified}.");

        return self::SUCCESS;
    }

    private function alreadyNotified(int $userId, string $event, int|string $key): bool
    {
        return DatabaseNotification::query()
            ->where('notifiable_id', $userId)
            ->where('type', OperationalNotification::class)
            ->where('data->event', $event)
            ->where('data->key', (string) $key)
            ->whereDate('created_at', today())
            ->exists();
    }
}
