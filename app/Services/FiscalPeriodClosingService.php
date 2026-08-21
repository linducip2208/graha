<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\BankStatementLine;
use App\Models\FiscalPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalPeriodClosingService
{
    public function __construct(private AuditTrail $audit, private ManufacturingWipService $manufacturingWip) {}

    public function close(FiscalPeriod $period, User $actor): FiscalPeriod
    {
        return DB::transaction(function () use ($period, $actor) {
            $period = FiscalPeriod::lockForUpdate()->findOrFail($period->id);
            throw_unless($period->status === 'open', ValidationException::withMessages(['period' => 'Periode tidak open.']));
            $approved = ApprovalRequest::where('approvable_type', FiscalPeriod::class)->where('approvable_id', $period->id)->where('status', 'approved')->exists();
            throw_unless($approved, ValidationException::withMessages(['approval' => 'Period closing belum disetujui.']));
            $unreconciled = BankStatementLine::whereHas('bankAccount', fn ($q) => $q->where('company_id', $period->company_id))->whereBetween('transaction_date', [$period->starts_at, $period->ends_at])->where('status', 'unreconciled')->exists();
            throw_if($unreconciled, ValidationException::withMessages(['bank' => 'Masih ada bank statement belum direkonsiliasi.']));
            throw_if($this->manufacturingWip->reconcile($period->company_id)->contains('anomaly', true), ValidationException::withMessages(['manufacturing_wip' => 'Masih ada production order terminal dengan residual WIP. Rekonsiliasi biaya manufaktur sebelum menutup periode.']));
            $period->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $actor->id]);
            $this->audit->record($period->company_id, $actor->id, 'accounting.period_closed', $period);

            return $period->refresh();
        }, 3);
    }
}
