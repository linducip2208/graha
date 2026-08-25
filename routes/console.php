<?php

use App\Services\QmsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (QmsService $service): void {
    Company::query()->select('id')->chunkById(100, fn ($companies) => $companies->each(fn ($company) => $service->refreshEvidenceStatus($company->id)));
})->name('qms-evidence-expiry')->dailyAt('01:30')->withoutOverlapping();

Schedule::command('approvals:monitor-sla')->name('approval-sla-monitor')->hourly()->withoutOverlapping();
Schedule::command('inventory:notify-low-stock')->name('low-stock-notify')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('qms:notify-due')->name('qms-due-notify')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('journals:post-recurring')->name('recurring-journal-post')->dailyAt('01:00')->withoutOverlapping();

Schedule::command('backup:database --retention-days=14')->name('database-backup')->dailyAt('02:15')->withoutOverlapping()->onOneServer();
