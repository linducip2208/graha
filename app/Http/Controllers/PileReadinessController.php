<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\PileBottomCleaningInspection;
use App\Services\AuditTrail;
use App\Services\PileReadinessService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

/**
 * Readiness engine endpoints (ADR-073) + bottom cleaning inspection gate.
 * Semua aksi teraudit; readiness TIDAK mengubah status pile — hanya melapor.
 */
class PileReadinessController extends Controller
{
    public function __construct(private AuditTrail $audit) {}

    private function authorizePile(Request $request, BoredPile $pile, CurrentCompany $current): void
    {
        abort_unless($pile->project()->where('company_id', $current->id())->exists(), 404);
        abort_unless($request->user()->hasPermission('project.manage', $current->id()), 403);
    }

    public function check(Request $request, BoredPile $pile, CurrentCompany $current, PileReadinessService $service)
    {
        $this->authorizePile($request, $pile, $current);
        $kind = $request->validate(['kind' => ['required', 'in:drill,cast']])['kind'];
        $check = $service->recordCheck($pile, $kind, $request->user());
        $this->audit->record($pile->project->company_id, $request->user()->id, 'pile_readiness_checked', $check);

        return back()->with('status', 'Readiness '.($kind === 'drill' ? 'Ready to Drill' : 'Ready to Cast').": {$check->status} — snapshot tersimpan.");
    }

    /** Attestasi lapangan (platform siap / booking beton) — timestamp + audit. */
    public function attest(Request $request, BoredPile $pile, CurrentCompany $current)
    {
        $this->authorizePile($request, $pile, $current);
        $field = $request->validate(['attestation' => ['required', 'in:platform,concrete_booking']])['attestation'];
        $column = $field === 'platform' ? 'platform_ready_at' : 'concrete_booking_confirmed_at';
        $pile->update([$column => now()]);
        $this->audit->record($pile->project->company_id, $request->user()->id, 'pile_attestation_recorded', $pile);

        return back()->with('status', 'Attestasi terekam dan diaudit.');
    }

    public function storeCleaning(Request $request, BoredPile $pile, CurrentCompany $current)
    {
        $this->authorizePile($request, $pile, $current);
        $data = $request->validate([
            'method' => ['required', 'in:'.implode(',', PileBottomCleaningInspection::METHODS)],
            'sediment_thickness_mm' => ['nullable', 'decimal:0,2', 'min:0'],
            'cleaned_at' => ['nullable', 'date'],
            'inspected_at' => ['required', 'date'],
            'witnessed_by' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'max:2000'],
        ]);
        $inspection = PileBottomCleaningInspection::create([
            'company_id' => $pile->project->company_id,
            'bored_pile_id' => $pile->id,
            'method' => $data['method'],
            'sediment_thickness_mm' => $data['sediment_thickness_mm'] ?? null,
            'cleaned_at' => filled($data['cleaned_at'] ?? null) ? $data['cleaned_at'] : null,
            'inspected_at' => $data['inspected_at'],
            'inspected_by' => $request->user()->id,
            'witnessed_by' => $data['witnessed_by'] ?? null,
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);
        $this->audit->record($pile->project->company_id, $request->user()->id, 'pile_cleaning_inspected', $inspection);

        return back()->with('status', 'Record bottom cleaning tersimpan — menunggu keputusan inspektur.');
    }

    /** Keputusan accept/reject oleh QA inspector (permission qms.verify). */
    public function decideCleaning(Request $request, PileBottomCleaningInspection $inspection, CurrentCompany $current)
    {
        abort_unless($inspection->company_id === $current->id(), 404);
        abort_unless($request->user()->hasPermission('qms.verify', $current->id()), 403);
        $decision = $request->validate(['decision' => ['required', 'in:accepted,rejected'], 'notes' => ['nullable', 'max:2000']]);
        $inspection->update([
            'status' => $decision['decision'],
            'notes' => filled($decision['notes'] ?? null) ? $decision['notes'] : $inspection->notes,
        ]);
        if ($decision['decision'] === 'rejected') {
            // Rejection tidak otomatis me-rollback pile — engineering memutuskan.
            $this->audit->record($current->id(), $request->user()->id, 'pile_cleaning_rejected', $inspection);
        } else {
            $this->audit->record($current->id(), $request->user()->id, 'pile_cleaning_accepted', $inspection);
        }

        return back()->with('status', "Keputusan inspeksi cleaning: {$decision['decision']}.");
    }
}
