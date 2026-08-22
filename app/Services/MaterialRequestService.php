<?php

namespace App\Services;

use App\Models\AccountingMapping;
use App\Models\MaterialRequest;
use App\Models\ProjectCostLedger;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialRequestService
{
    public function __construct(
        private InventoryService $inventory,
        private AccountingService $accounting,
        private AuditTrail $audit
    ) {}

    public function create(int $companyId, array $data, array $lines, User $actor): MaterialRequest
    {
        return DB::transaction(function () use ($companyId, $data, $lines, $actor) {
            throw_if(MaterialRequest::where('company_id', $companyId)->where('number', $data['number'])->exists(), ValidationException::withMessages(['number' => 'Nomor permintaan sudah dipakai.']));
            throw_if($lines === [], ValidationException::withMessages(['lines' => 'Minimal satu item.']));
            foreach ($lines as $line) {
                throw_if(bccomp((string) $line['quantity'], '0', 4) !== 1, ValidationException::withMessages(['lines' => 'Kuantitas harus positif.']));
            }
            $request = MaterialRequest::create([...$data, 'company_id' => $companyId, 'status' => 'requested', 'requested_by' => $actor->id]);
            foreach ($lines as $line) {
                $request->lines()->create(['item_id' => $line['item_id'], 'quantity' => $line['quantity']]);
            }
            $this->audit->record($companyId, $actor->id, 'inventory.material_requested', $request);

            return $request->load('lines');
        }, 3);
    }

    public function approve(MaterialRequest $request, User $actor): MaterialRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            $request = MaterialRequest::lockForUpdate()->findOrFail($request->id);
            throw_unless($request->status === 'requested', ValidationException::withMessages(['status' => 'Permintaan sudah diproses.']));
            throw_if((int) $request->requested_by === (int) $actor->id, ValidationException::withMessages(['approver' => 'Pemohon tidak boleh menyetujui sendiri.']));
            $request->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->audit->record($request->company_id, $actor->id, 'inventory.material_approved', $request);

            return $request->refresh();
        }, 3);
    }

    /** Pengembalian sebagian material ke gudang: stok masuk kembali dengan unit cost issue terakhir, jurnal dibalik, dan project cost ledger dikoreksi negatif. */
    public function returnLine(MaterialRequest $request, int $lineId, string $quantity, User $actor): MaterialRequest
    {
        return DB::transaction(function () use ($request, $lineId, $quantity, $actor) {
            $request = MaterialRequest::lockForUpdate()->findOrFail($request->id);
            throw_unless($actor->companies()->whereKey($request->company_id)->where('company_user.is_active', true)->exists(), ValidationException::withMessages(['company' => 'Anda bukan anggota aktif perusahaan ini.']));
            throw_unless(in_array($request->status, ['approved'], true), ValidationException::withMessages(['status' => 'Permintaan belum di-approve.']));
            $line = $request->lines()->whereKey($lineId)->firstOrFail();
            throw_if(bccomp($quantity, '0', 4) !== 1, ValidationException::withMessages(['quantity' => 'Kuantitas harus positif.']));
            throw_if(bccomp($quantity, (string) $line->issued_quantity, 4) === 1, ValidationException::withMessages(['quantity' => 'Pengembalian melebihi jumlah yang sudah diterbitkan.']));

            $lastIssueUnitCost = (string) (StockMovement::where([
                'company_id' => $request->company_id,
                'item_id' => $line->item_id,
                'reference_type' => 'material_request',
                'reference_id' => (string) $request->id,
                'movement_type' => 'issue',
            ])->orderByDesc('id')->value('unit_cost') ?? '0');

            $returnSeq = StockMovement::where('company_id', $request->company_id)->where('item_id', $line->item_id)->where('reference_id', (string) $request->id)->where('movement_type', 'return_in')->count() + 1;
            $this->inventory->post([
                'company_id' => $request->company_id,
                'item_id' => $line->item_id,
                'warehouse_id' => $request->warehouse_id,
                'warehouse_bin_id' => null,
            ], 'return_in', $quantity, "material-return:{$request->id}:{$line->id}:{$returnSeq}", $actor, ['type' => 'material_return', 'id' => $request->id], $lastIssueUnitCost);

            $maps = AccountingMapping::where('company_id', $request->company_id)->where('event_type', 'material_issue')->get()->keyBy('entry_side');
            if ($maps->has('debit') && $maps->has('credit') && bccomp(bcmul($quantity, $lastIssueUnitCost, 2), '0', 2) === 1) {
                $reversalAmount = bcmul($quantity, $lastIssueUnitCost, 2);
                $journal = $this->accounting->post(
                    $request->company_id,
                    now()->toDateString(),
                    'material_return',
                    (string) $request->id,
                    'Pengembalian material '.$request->number,
                    [
                        ['account_id' => $maps['credit']->account_id, 'debit' => $reversalAmount, 'credit' => '0'],
                        ['account_id' => $maps['debit']->account_id, 'debit' => '0', 'credit' => $reversalAmount, 'project_id' => $request->project_id],
                    ],
                    "material-return-journal:{$request->id}:{$line->id}:{$returnSeq}",
                    $actor
                );
                $gudangEntry = $journal->entries->first(fn ($e) => (string) $e->debit === $reversalAmount);
                ProjectCostLedger::create([
                    'company_id' => $request->company_id,
                    'project_id' => $request->project_id,
                    'journal_entry_id' => $gudangEntry?->id ?? $journal->entries->first()->id,
                    'cost_type' => 'actual',
                    'amount' => bcmul($reversalAmount, '-1', 2),
                    'cost_date' => now()->toDateString(),
                ]);
            }
            $line->decrement('issued_quantity', $quantity);
            $this->audit->record($request->company_id, $actor->id, 'inventory.material_returned', $request);

            return $request->refresh();
        }, 3);
    }

    /** Issue sebagian atau penuh per baris; jurnal Biaya Material (D) / Gudang (K) berdimensi proyek. */
    public function issue(MaterialRequest $request, User $actor): MaterialRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            $request = MaterialRequest::lockForUpdate()->findOrFail($request->id);
            throw_unless($actor->companies()->whereKey($request->company_id)->where('company_user.is_active', true)->exists(), ValidationException::withMessages(['company' => 'Anda bukan anggota aktif perusahaan ini.']));
            throw_unless($request->status === 'approved', ValidationException::withMessages(['status' => 'Permintaan belum di-approve.']));
            $pendingLines = $request->lines()->whereColumn('issued_quantity', '<', 'quantity')->get();
            throw_if($pendingLines->isEmpty(), ValidationException::withMessages(['issue' => 'Semua baris sudah diterbitkan.']));
            $totalCost = '0';
            $sequence = (int) (MaterialRequest::whereKey($request->id)->max('id'));
            foreach ($pendingLines as $line) {
                $quantity = $line->remaining();
                $movement = $this->inventory->post([
                    'company_id' => $request->company_id,
                    'item_id' => $line->item_id,
                    'warehouse_id' => $request->warehouse_id,
                    'warehouse_bin_id' => null,
                    'project_id' => $request->project_id,
                    'bored_pile_id' => $request->bored_pile_id,
                ], 'issue', $quantity, "material-issue:{$request->id}:{$line->id}", $actor, ['type' => 'material_request', 'id' => $request->id], '0');
                $unitCost = (string) $movement->unit_cost;
                $line->update(['issued_quantity' => $quantity]);
                $totalCost = bcadd($totalCost, bcmul($quantity, bccomp($unitCost, '0', 4) === 1 ? $unitCost : '0', 2), 2);
            }
            if (bccomp($totalCost, '0', 2) === 1) {
                $maps = AccountingMapping::where('company_id', $request->company_id)->where('event_type', 'material_issue')->get()->keyBy('entry_side');
                throw_unless($maps->has('debit') && $maps->has('credit'), ValidationException::withMessages(['mapping' => 'Mapping material_issue belum lengkap.']));
                $this->accounting->post(
                    $request->company_id,
                    now()->toDateString(),
                    'material_issue',
                    (string) $request->id,
                    'Pengeluaran material '.$request->number,
                    [
                        ['account_id' => $maps['debit']->account_id, 'debit' => $totalCost, 'credit' => '0', 'project_id' => $request->project_id],
                        ['account_id' => $maps['credit']->account_id, 'debit' => '0', 'credit' => $totalCost],
                    ],
                    "material-issue-journal:{$request->id}",
                    $actor
                );
            }
            $this->audit->record($request->company_id, $actor->id, 'inventory.material_issued', $request);

            return $request->refresh();
        }, 3);
    }
}
