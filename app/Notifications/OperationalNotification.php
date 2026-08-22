<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OperationalNotification extends Notification
{
    public function __construct(public string $event, public array $payload) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['event' => $this->event] + $this->payload;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = match ($this->event) {
            'stock_low' => "Stok {$this->payload['item']} di gudang {$this->payload['warehouse']} tinggal {$this->payload['quantity']} (minimum {$this->payload['minimum']}).",
            'capa_due' => "Tindakan korektif \"{$this->payload['title']}\" jatuh tempo {$this->payload['due_at']}.",
            'ncr_overdue' => "NCR \"{$this->payload['title']}\" melewati tenggat {$this->payload['due_at']}.",
            default => ($this->payload['message'] ?? 'Pemberitahuan operasional.'),
        };

        return (new MailMessage)
            ->subject('[ERP] '.$this->subject())
            ->greeting('Halo '.$notifiable->name.',')
            ->line($message)
            ->line('Pesan otomatis dari '.config('app.name').'.');
    }

    private function subject(): string
    {
        return match ($this->event) {
            'stock_low' => 'Stok di bawah minimum',
            'capa_due', 'ncr_overdue' => 'Tindakan mutu mendekati/melewati tenggat',
            default => 'Pemberitahuan operasional',
        };
    }
}
