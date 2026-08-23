<?php

namespace Tests\Feature\Workspace;

use App\Models\Account;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\NumberSequence;
use App\Models\Tender;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\ApprovalEngine;
use App\Services\QmsService;
use App\Services\TenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $this->actor = User::factory()->create();
        $this->company->users()->attach($this->actor, ['is_default' => true, 'is_active' => true]);
    }

    /** Pemindai kelengkapan audit: alur kritis tata kelola wajib meninggalkan jejak immutable. */
    public function test_critical_governance_flows_leave_audit_trail(): void
    {
        // 1. Approval engine: submission + keputusan.
        $approver = User::factory()->create();
        $this->company->users()->attach($approver, ['is_active' => true]);
        $roleId = DB::table('roles')->insertGetId(['company_id' => $this->company->id, 'code' => 'audit-approver', 'name' => 'Approver Audit', 'is_system' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_role')->insert(['company_user_id' => DB::table('company_user')->where('user_id', $approver->id)->where('company_id', $this->company->id)->value('id'), 'role_id' => $roleId]);
        $workflow = ApprovalWorkflow::create(['company_id' => $this->company->id, 'name' => 'Audit WF', 'document_type' => 'company', 'is_active' => true]);
        ApprovalStep::create(['approval_workflow_id' => $workflow->id, 'sequence' => 1, 'role_id' => $roleId, 'mode' => 'any']);
        $subject = $this->company;
        $engine = app(ApprovalEngine::class);
        $request = $engine->submit($workflow, $subject, $this->actor, 'audit-key-1');
        $engine->approve($request, $approver);

        // 2. Tender outcome.
        $customer = Customer::create(['company_id' => $this->company->id, 'code' => 'C-AUD', 'name' => 'Pelanggan Audit']);
        $tender = Tender::create(['company_id' => $this->company->id, 'customer_id' => $customer->id, 'number' => 'T-AUD', 'year' => 2026, 'project_name' => 'Proyek Audit', 'status' => 'bidding', 'created_by' => $this->actor->id]);
        app(TenderService::class)->recordOutcome($tender, $this->actor, 'won', ['announced_at' => '2026-08-23']);

        // 3. QMS risk register.
        app(QmsService::class)->createRisk(['company_id' => $this->company->id, 'type' => 'risk', 'code' => 'RISK-AUD', 'title' => 'Risiko Audit', 'description' => 'Uji jejak audit', 'likelihood' => 3, 'impact' => 4, 'owner_id' => $this->actor->id], $this->actor);

        foreach (['approval.submitted', 'approval.approve', 'tender.won', 'qms.risk_created'] as $event) {
            $this->assertDatabaseHas('audit_logs', ['company_id' => $this->company->id, 'event' => $event, 'actor_id' => $event === 'approval.approve' ? $approver->id : $this->actor->id]);
        }
    }

    /** Posting jurnal (keuangan) wajib ter-audit dan idempoten. */
    public function test_financial_posting_leaves_audit_trail(): void
    {
        FiscalPeriod::create(['company_id' => $this->company->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
        NumberSequence::create(['company_id' => $this->company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);
        $debit = Account::create(['company_id' => $this->company->id, 'code' => 'CASH', 'name' => 'Kas', 'type' => 'asset', 'normal_balance' => 'debit']);
        $credit = Account::create(['company_id' => $this->company->id, 'code' => 'REV', 'name' => 'Pendapatan', 'type' => 'revenue', 'normal_balance' => 'credit']);

        app(AccountingService::class)->post($this->company->id, '2026-08-23', 'manual', 'AUD-1', 'Uji audit', [
            ['account_id' => $debit->id, 'debit' => '500.00', 'credit' => '0'],
            ['account_id' => $credit->id, 'debit' => '0', 'credit' => '500.00'],
        ], 'audit-key-jurnal', $this->actor);

        $this->assertDatabaseHas('audit_logs', ['company_id' => $this->company->id, 'event' => 'accounting.journal_posted']);
    }

    /** Setiap baris audit mencatat aktor — tidak boleh ada event tanpa pelaku. */
    public function test_every_audit_row_records_actor(): void
    {
        $customer = Customer::create(['company_id' => $this->company->id, 'code' => 'C-ACT', 'name' => 'Pelanggan Aktor']);
        $tender = Tender::create(['company_id' => $this->company->id, 'customer_id' => $customer->id, 'number' => 'T-ACT', 'year' => 2026, 'project_name' => 'Proyek Aktor', 'status' => 'bidding', 'created_by' => $this->actor->id]);
        app(TenderService::class)->recordOutcome($tender, $this->actor, 'lost', ['announced_at' => '2026-08-23', 'primary_reason' => 'Harga']);

        $rows = DB::table('audit_logs')->where('company_id', $this->company->id)->get();
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertNotNull($row->actor_id, 'Event '.$row->event.' tanpa aktor.');
            $this->assertNotNull($row->event);
        }
    }
}
