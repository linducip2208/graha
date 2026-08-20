<?php

use App\Models\ApprovalRequest;
use App\Models\Company;
use App\Services\QmsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (QmsService $service): void {
    Company::query()->select('id')->chunkById(100, fn ($companies) => $companies->each(fn ($company) => $service->refreshEvidenceStatus($company->id)));
})->name('qms-evidence-expiry')->dailyAt('01:30')->withoutOverlapping();

Schedule::call(function (): void {
    ApprovalRequest::whereIn('status', ['submitted', 'in_progress'])->whereNotNull('due_at')->where('due_at', '<', now())
        ->selectRaw('company_id, count(*) as total')->groupBy('company_id')->get()
        ->each(fn ($row) => Log::warning('Approval melewati SLA.', ['company_id' => $row->company_id, 'total' => $row->total]));
})->name('approval-sla-monitor')->hourly()->withoutOverlapping();
