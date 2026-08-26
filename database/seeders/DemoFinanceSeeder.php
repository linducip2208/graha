<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\FiscalPeriod;
use App\Models\NumberSequence;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\Role;
use App\Models\TaxRate;
use App\Services\ApprovalEngine;
use App\Services\CashBankService;
use App\Services\ProgressBillingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo finance (ADR-079): COA, mapping, tarif pajak, periode fiskal,
 * billing → AR → penerimaan dengan bukti potong, dan statement bank.
 * Jurnal dibuat lewat service nyata sehingga selalu balanced.
 */
class DemoFinanceSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = DemoDataSeeder::company()->id;
        $finance = DemoDataSeeder::user('finance@grahapondasi.test');
        $director = DemoDataSeeder::user('direktur@grahapondasi.test');

        // --- COA ---
        $blueprint = [
            ['1-1000', 'Kas & Bank', 'asset', 'debit'],
            ['1-1100', 'Piutang Retensi', 'asset', 'debit'],
            ['1-1200', 'Pajak Masukan (PPN)', 'asset', 'debit'],
            ['1-1300', 'Pajak Dibayar di Muka (PPh)', 'asset', 'debit'],
            ['1-2000', 'Material Gudang', 'asset', 'debit'],
            ['2-1000', 'Uang Muka Pelanggan', 'liability', 'credit'],
            ['2-2000', 'GRNI', 'liability', 'credit'],
            ['2-2100', 'Utang Usaha (AP)', 'liability', 'credit'],
            ['2-2200', 'PPN Keluaran', 'liability', 'credit'],
            ['2-2300', 'Hutang PPh Dipotong', 'liability', 'credit'],
            ['4-1000', 'Pendapatan Kontrak (Revenue)', 'revenue', 'credit'],
            ['5-2000', 'Biaya Material Proyek', 'expense', 'debit'],
        ];
        $accounts = [];
        foreach ($blueprint as [$code, $name, $type, $normal]) {
            $accounts[$code] = Account::firstOrCreate(['company_id' => $companyId, 'code' => $code], ['name' => $name, 'type' => $type, 'normal_balance' => $normal]);
        }

        $matrix = [
            ['progress_billing', 'ar_debit', '1-1000'], ['progress_billing', 'retention_debit', '1-1100'],
            ['progress_billing', 'advance_debit', '2-1000'], ['progress_billing', 'revenue_credit', '4-1000'],
            ['progress_billing', 'tax_credit', '2-2200'], ['customer_receipt', 'ar_credit', '1-1000'],
            ['customer_receipt', 'withholding_debit', '1-1300'], ['goods_receipt', 'debit', '1-2000'],
            ['goods_receipt', 'credit', '2-2000'], ['material_issue', 'debit', '5-2000'],
            ['material_issue', 'credit', '1-2000'], ['vendor_invoice', 'debit', '5-2000'],
            ['vendor_invoice', 'credit', '2-2100'], ['vendor_invoice', 'tax_debit', '1-1200'],
            ['vendor_payment', 'ap_debit', '2-2100'], ['vendor_payment', 'withholding_credit', '2-2300'],
        ];
        foreach ($matrix as [$event, $side, $code]) {
            AccountingMapping::firstOrCreate(['company_id' => $companyId, 'event_type' => $event, 'entry_side' => $side], ['account_id' => $accounts[$code]->id]);
        }

        foreach ([
            ['PPN-KELUARAN', 'PPN Keluaran 11%', 'ppn_output', '11'],
            ['PPN-MASUKAN', 'PPN Masukan 11%', 'ppn_input', '11'],
            ['PPH-FINAL', 'PPh Final Konstruksi 4(2) 1.5%', 'withholding', '1.5'],
            ['PPH23', 'PPh Pasal 23 2%', 'withholding', '2'],
        ] as [$code, $name, $kind, $percent]) {
            TaxRate::firstOrCreate(['company_id' => $companyId, 'code' => $code], ['name' => $name, 'kind' => $kind, 'rate_percent' => $percent]);
        }

        FiscalPeriod::firstOrCreate(['company_id' => $companyId, 'name' => 'FY 2026'], ['starts_at' => now()->startOfYear()->toDateString(), 'ends_at' => now()->endOfYear()->toDateString(), 'status' => 'open']);
        NumberSequence::firstOrCreate(['company_id' => $companyId, 'document_type' => 'journal'], ['prefix' => 'JV', 'padding' => 5, 'last_reset_year' => now()->year]);

        // --- Billing flow (proyek healthy) ---
        $project = Project::where('company_id', $companyId)->where('code', 'PRJ-2601')->first();
        if ($project === null) {
            return;
        }
        $bank = BankAccount::firstOrCreate(['company_id' => $companyId, 'code' => 'BCA-OPS'], [
            'account_id' => $accounts['1-1000']->id, 'bank_name' => 'BCA',
            'account_name' => 'PT Graha Pondasi Operasional', 'account_number' => '5410123456', 'currency' => 'IDR',
        ]);
        $ppnOut = TaxRate::where('company_id', $companyId)->where('code', 'PPN-KELUARAN')->first();
        $pphFinal = TaxRate::where('company_id', $companyId)->where('code', 'PPH-FINAL')->first();
        $service = app(ProgressBillingService::class);

        $billingOne = ProgressBilling::where('company_id', $companyId)->where('number', 'PB-DEMO-001')->first();
        if (! $billingOne) {
            $billingOne = $service->create($project, [
                'number' => 'PB-DEMO-001', 'billing_date' => now()->subDays(15)->toDateString(), 'due_date' => now()->addDays(15)->toDateString(),
                'progress_percent' => '20', 'gross_amount' => '500000000', 'retention_percent' => '5', 'advance_recovery' => '0',
                'tax_rate_id' => (string) $ppnOut->id, 'idempotency_key' => 'demo-billing-1',
            ], $finance);
            $workflow = ApprovalWorkflow::firstOrCreate(['company_id' => $companyId, 'name' => 'Approval Billing', 'document_type' => 'progress_billing']);
            ApprovalStep::firstOrCreate(['approval_workflow_id' => $workflow->id, 'sequence' => 1], ['action' => 'approve', 'mode' => 'any', 'role_id' => Role::where('company_id', $companyId)->where('code', 'director')->firstOrFail()->id, 'sla_hours' => 48]);
            ApprovalRequest::firstOrCreate(['company_id' => $companyId, 'idempotency_key' => 'demo-billing-1-approval'], [
                'approval_workflow_id' => $workflow->id, 'approvable_type' => ProgressBilling::class, 'approvable_id' => $billingOne->id,
                'submitted_by' => $finance->id, 'status' => 'approved', 'current_sequence' => 1, 'amount' => '500000000', 'currency' => 'IDR', 'submitted_at' => now(), 'completed_at' => now(),
            ]);
            $service->activateApproved($billingOne, $director);
            $service->post($billingOne->refresh(), $finance);
            $billingOne = $billingOne->refresh();

            app(CashBankService::class)->receiveCustomer($billingOne, $bank, '257400000', now()->subDays(4)->toDateString(), 'RCV-DEMO-001', 'TRF-IN-77231', 'demo-rcv-001', $finance, [
                'tax_rate_id' => (string) $pphFinal->id, 'bukti_potong_number' => 'BP-42/'.now()->format('Y').'/WKBM', 'bukti_potong_date' => now()->subDays(4)->toDateString(),
            ]);
            app(CashBankService::class)->receiveCustomer($billingOne->refresh(), $bank, '268739000', now()->subDays(2)->toDateString(), 'RCV-DEMO-002', 'TRF-IN-77240', 'demo-rcv-002', $finance);
        } elseif (! DB::table('journals')->where('company_id', $companyId)->where('source_type', 'progress_billing')->exists()) {
            $service->post($billingOne, $finance);
        }

        $billingTwo = ProgressBilling::where('company_id', $companyId)->where('number', 'PB-DEMO-002')->first();
        if (! $billingTwo) {
            $billingTwo = $service->create($project, [
                'number' => 'PB-DEMO-002', 'billing_date' => now()->subDays(2)->toDateString(), 'due_date' => now()->addDays(28)->toDateString(),
                'progress_percent' => '35', 'gross_amount' => '375000000', 'retention_percent' => '5', 'advance_recovery' => '25000000',
                'tax_rate_id' => (string) $ppnOut->id, 'idempotency_key' => 'demo-billing-2',
            ], $finance);
            $workflow = ApprovalWorkflow::where('company_id', $companyId)->where('name', 'Approval Billing')->where('document_type', 'progress_billing')->firstOrFail();
            app(ApprovalEngine::class)->submit($workflow, $billingTwo, $finance, 'demo-billing-2-submit');
            $billingTwo->update(['status' => 'pending_approval']);
        }

        // --- Portofolio billing lintas proyek & lintas bulan (trend pendapatan bermakna) ---
        $workflow = ApprovalWorkflow::firstOrCreate(['company_id' => $companyId, 'name' => 'Approval Billing', 'document_type' => 'progress_billing']);
        ApprovalStep::firstOrCreate(['approval_workflow_id' => $workflow->id, 'sequence' => 1], ['action' => 'approve', 'mode' => 'any', 'role_id' => Role::where('company_id', $companyId)->where('code', 'director')->firstOrFail()->id, 'sla_hours' => 48]);

        // [proyek, number, hari lalu, progress %, gross, posted?, idempotency suffix]
        $portfolio = [
            ['PRJ-2601', 'PB-DEMO-003', 6, '15', '375000000', true, 'pb-demo-3'],
            ['PRJ-2602', 'PB-KRW-001', 75, '15', '270000000', true, 'pb-krw-1'],
            ['PRJ-2602', 'PB-KRW-002', 45, '15', '270000000', true, 'pb-krw-2'],
            ['PRJ-2602', 'PB-KRW-003', 9, '12', '216000000', false, 'pb-krw-3'],
            ['PRJ-2603', 'PBS-2603-001', 110, '30', '870000000', true, 'pbs-1'],
            ['PRJ-2603', 'PBS-2603-002', 75, '30', '870000000', true, 'pbs-2'],
            ['PRJ-2603', 'PBS-2603-003', 28, '25', '725000000', true, 'pbs-3'],
        ];
        foreach ($portfolio as [$projectCode, $number, $daysAgo, $progress, $gross, $posted, $suffix]) {
            if (ProgressBilling::where('company_id', $companyId)->where('number', $number)->exists()) {
                continue;
            }
            $billingProject = Project::where('company_id', $companyId)->where('code', $projectCode)->first();
            if ($billingProject === null) {
                continue;
            }
            $billing = $service->create($billingProject, [
                'number' => $number,
                'billing_date' => now()->subDays($daysAgo)->toDateString(),
                'due_date' => now()->subDays($daysAgo)->addDays(30)->toDateString(),
                'progress_percent' => $progress, 'gross_amount' => $gross,
                'retention_percent' => '5', 'advance_recovery' => '0',
                'tax_rate_id' => (string) $ppnOut->id, 'idempotency_key' => 'demo-billing-'.$suffix,
            ], $finance);
            if ($posted) {
                ApprovalRequest::firstOrCreate(['company_id' => $companyId, 'idempotency_key' => 'demo-billing-'.$suffix.'-approval'], [
                    'approval_workflow_id' => $workflow->id, 'approvable_type' => ProgressBilling::class, 'approvable_id' => $billing->id,
                    'submitted_by' => $finance->id, 'status' => 'approved', 'current_sequence' => 1,
                    'amount' => $gross, 'currency' => 'IDR', 'submitted_at' => now(), 'completed_at' => now(),
                ]);
                $service->activateApproved($billing, $director);
                $service->post($billing->refresh(), $finance);
            } else {
                app(ApprovalEngine::class)->submit($workflow, $billing, $finance, 'demo-billing-'.$suffix.'-submit');
                $billing->update(['status' => 'pending_approval']);
            }
        }

        foreach ([['TRF-IN-77231', now()->subDays(4), '257400000', 'Transfer masuk klien WKBM'], ['TRF-IN-77240', now()->subDays(2), '268739000', 'Transfer masuk klien WKBM'], ['TRF-99182X', now()->subDays(3), '-41625000', 'Transfer keluar vendor besi']] as [$reference, $date, $amount, $description]) {
            BankStatementLine::firstOrCreate(['bank_account_id' => $bank->id, 'reference' => $reference], [
                'transaction_date' => $date->toDateString(), 'description' => $description, 'amount' => $amount,
            ]);
        }
    }
}
