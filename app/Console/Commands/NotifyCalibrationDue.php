<?php

namespace App\Console\Commands;

use App\Models\CalibrationRecord;
use App\Models\User;
use App\Notifications\OperationalNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class NotifyCalibrationDue extends Command
{
    protected $signature = 'qms:notify-calibration';

    protected $description = 'Mengirim notifikasi alat ukur jatuh tempo kalibrasi (≤30 hari) atau overdue (sekali per record/hari)';

    public function handle(): int
    {
        $notified = 0;
        $horizon = now()->addDays(30)->toDateString();

        CalibrationRecord::with('equipment')->whereDate('next_due_at', '<=', $horizon)
            ->orderBy('next_due_at')
            ->chunkById(100, function ($records) use (&$notified): void {
                foreach ($records as $record) {
                    $overdue = $record->statusNow() === 'overdue';
                    $event = $overdue ? 'calibration_overdue' : 'calibration_due';
                    $recipient = User::find($record->created_by);
                    if (! $recipient || $this->alreadyNotified($recipient->id, $event, $record->id)) {
                        continue;
                    }
                    $recipient->notify(new OperationalNotification($event, [
                        'key' => (string) $record->id,
                        'calibration_id' => $record->id,
                        'title' => $record->instrument_name.' ('.($record->equipment?->code ?? 'alat').')',
                        'due_at' => $record->next_due_at->format('d/m/Y'),
                        'url' => '/admin/calibrations',
                    ]));
                    $notified++;
                }
            });

        $this->info("Notifikasi kalibrasi terkirim: {$notified}.");

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
