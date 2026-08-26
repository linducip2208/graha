<?php

namespace App\Http\Controllers;

use App\Models\BackupRecord;
use App\Models\SystemHealthState;
use App\Services\AuditTrail;
use App\Services\BackupService;
use App\Services\SystemHealthService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

class SystemOperationsController extends Controller
{
    public function health(Request $request, CurrentCompany $current, SystemHealthService $health)
    {
        $this->authorizePlatform($request, 'system.view');

        return view('settings.system-health', $health->checks($current->id()) + [
            'canManageQueue' => $request->user()->hasPlatformPermission('queue.manage'),
            'canTestMail' => $request->user()->hasPlatformPermission('mail.test'),
        ]);
    }

    public function testMail(Request $request, CurrentCompany $current, AuditTrail $audit)
    {
        $this->authorizePlatform($request, 'mail.test');
        $data = $request->validate(['recipient' => ['required', 'email'], 'subject' => ['nullable', 'string', 'max:120']]);
        try {
            Mail::raw('Email uji System Health Graha. Tidak ada credential atau data bisnis pada pesan ini.', fn ($mail) => $mail->to($data['recipient'])->subject($data['subject'] ?: 'Graha System Health Test'));
            $status = 'healthy';
            $message = 'Email uji berhasil diserahkan ke mail transport.';
        } catch (\Throwable) {
            $status = 'critical';
            $message = 'Email tidak dapat dikirim. Periksa konfigurasi mail pada server.';
        }
        SystemHealthState::updateOrCreate(['key' => 'mail'], ['last_tested_at' => now(), 'status' => $status, 'message' => $message]);
        $audit->record($current->id(), $request->user()->id, 'system.mail_tested', metadata: ['result' => $status, 'recipient_domain' => str($data['recipient'])->after('@')->toString()]);

        return back()->with('status', $message);
    }

    public function failedJob(Request $request, CurrentCompany $current, string $uuid, AuditTrail $audit)
    {
        $this->authorizePlatform($request, 'queue.manage');
        $action = $request->validate(['action' => ['required', 'in:retry,delete']])['action'];
        Artisan::call($action === 'retry' ? 'queue:retry' : 'queue:forget', ['id' => $uuid]);
        $audit->record($current->id(), $request->user()->id, 'system.failed_job_'.$action, metadata: ['job_uuid' => $uuid]);

        return back()->with('status', 'Aksi queue selesai dan teraudit.');
    }

    public function failedJobs(Request $request, CurrentCompany $current, AuditTrail $audit)
    {
        $this->authorizePlatform($request, 'queue.manage');
        $action = $request->validate(['action' => ['required', 'in:retry_all,delete_all']])['action'];
        Artisan::call($action === 'retry_all' ? 'queue:retry' : 'queue:flush', $action === 'retry_all' ? ['id' => ['all']] : []);
        $audit->record($current->id(), $request->user()->id, 'system.failed_jobs_'.$action);

        return back()->with('status', 'Aksi seluruh failed jobs selesai dan teraudit.');
    }

    public function backups(Request $request, CurrentCompany $current)
    {
        $this->authorizePlatform($request, 'backup.view');

        return view('settings.backups', ['backups' => BackupRecord::latest()->paginate(30), 'canManageBackup' => $request->user()->hasPlatformPermission('backup.manage')]);
    }

    public function createBackup(Request $request, CurrentCompany $current, BackupService $service, AuditTrail $audit)
    {
        $this->authorizePlatform($request, 'backup.manage');
        $record = $service->database($request->user()->id, 'Manual backup');
        $audit->record($current->id(), $request->user()->id, 'backup.created', $record, ['status' => $record->status, 'type' => $record->type]);

        return back()->with('status', $record->status === 'completed' ? 'Backup database berhasil.' : $record->last_error);
    }

    public function verifyBackup(Request $request, BackupRecord $backup, CurrentCompany $current, BackupService $service, AuditTrail $audit)
    {
        $this->authorizePlatform($request, 'backup.manage');
        $result = $service->verify($backup);
        $backup->update(['verified_at' => now(), 'verification_status' => $result['valid'] ? 'passed' : 'failed']);
        $audit->record($current->id(), $request->user()->id, 'backup.verified', $backup, ['result' => $backup->verification_status]);

        return back()->with('status', $result['message']);
    }

    private function authorizePlatform(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPlatformPermission($permission), 403);
    }
}
