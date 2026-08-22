<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalNotification extends Notification
{
    use Queueable;

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
            'approval_requested' => "Anda ditugaskan memeriksa {$this->payload['document']} {$this->payload['label']}.",
            'approval_advanced' => "{$this->payload['document']} {$this->payload['label']} disetujui dan lanjut ke tahap berikutnya.",
            'approval_approved' => "{$this->payload['document']} {$this->payload['label']} telah disetujui penuh.",
            'approval_rejected' => "{$this->payload['document']} {$this->payload['label']} ditolak.",
            'approval_revision_requested' => "Permintaan revisi untuk {$this->payload['document']} {$this->payload['label']}.",
            'approval_sla_overdue' => "Approval melewati batas SLA: {$this->payload['document']} {$this->payload['label']}.",
            default => $this->payload['label'] ?? 'Pemberitahuan approval.',
        };
        $mail = (new MailMessage)
            ->subject($this->subject())
            ->greeting('Halo '.$notifiable->name.',')
            ->line($message);
        if (! empty($this->payload['comment'])) {
            $mail->line('Komentar: '.$this->payload['comment']);
        }
        if (! empty($this->payload['url'])) {
            $mail->action('Buka Approval Center', url($this->payload['url']));
        }
        if (! empty($this->payload['due_at'])) {
            $mail->line('Batas SLA: '.$this->payload['due_at']);
        }

        return $mail->line('Pesan otomatis dari '.config('app.name').'.');
    }

    private function subject(): string
    {
        return match ($this->event) {
            'approval_requested' => '[Approval] Menunggu persetujuan Anda',
            'approval_advanced', 'approval_approved' => '[Approval] Dokumen disetujui',
            'approval_rejected' => '[Approval] Dokumen ditolak',
            'approval_revision_requested' => '[Approval] Permintaan revisi',
            'approval_sla_overdue' => '[SLA] Approval melewati batas waktu',
            default => '[Approval] Pemberitahuan',
        };
    }
}
