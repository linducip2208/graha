<?php

namespace App\Console\Commands;

use App\Models\BoredPile;
use App\Models\CompanySetting;
use App\Models\PileAcceptance;
use App\Models\StoredFile;
use Illuminate\Console\Command;

class VerifyFoundationIntegrity extends Command
{
    protected $signature = 'foundation:verify {--company= : Batasi company ID}';

    protected $description = 'Read-only anomaly detector untuk pile state, acceptance, evidence, dan identity';

    public function handle(): int
    {
        $rows = [];
        BoredPile::with(['project', 'tests', 'cleaningInspections'])
            ->when($this->option('company'), fn ($q, $id) => $q->whereHas('project', fn ($p) => $p->where('company_id', $id)))
            ->chunkById(500, function ($piles) use (&$rows) {
                foreach ($piles as $pile) {
                    $companyId = $pile->project?->company_id;
                    if (! $companyId) {
                        $rows[] = [$pile->id, 'ORPHAN_PROJECT'];

                        continue;
                    }
                    if ($pile->status === 'completed' && CompanySetting::val($companyId, 'require_pile_test_pass') === '1' && ! $pile->tests->contains('result_status', 'passed')) {
                        $rows[] = [$pile->id, 'COMPLETED_WITHOUT_PASSED_TEST'];
                    }
                    if (in_array($pile->status, ['casting', 'testing', 'completed'], true) && CompanySetting::val($companyId, 'require_cleaning_inspection') === '1' && ! $pile->cleaningInspections->contains('status', 'accepted')) {
                        $rows[] = [$pile->id, 'CAST_WITHOUT_ACCEPTED_CLEANING'];
                    }
                }
            });
        PileAcceptance::with(['pile.project'])->whereIn('status', ['accepted', 'conditional'])->chunkById(500, function ($acceptances) use (&$rows) {
            foreach ($acceptances as $acceptance) {
                if (! $acceptance->pile || (int) $acceptance->project_id !== (int) $acceptance->pile->project_id || (int) $acceptance->company_id !== (int) $acceptance->pile->project?->company_id) {
                    $rows[] = [$acceptance->bored_pile_id, 'ACCEPTANCE_SCOPE_MISMATCH'];
                }
                if (collect($acceptance->gate_checks)->contains(false)) {
                    $rows[] = [$acceptance->bored_pile_id, 'ACCEPTED_WITH_FAILED_GATE_SNAPSHOT'];
                }
            }
        });
        StoredFile::whereNotNull('bored_pile_id')->with(['boredPile.project'])->chunkById(500, function ($files) use (&$rows) {
            foreach ($files as $file) {
                if (! $file->boredPile || (int) $file->company_id !== (int) $file->boredPile->project?->company_id || ($file->project_id && (int) $file->project_id !== (int) $file->boredPile->project_id)) {
                    $rows[] = [$file->bored_pile_id, 'EVIDENCE_SCOPE_MISMATCH'];
                }
            }
        });
        $duplicates = BoredPile::selectRaw('project_id, pile_number, COUNT(*) total')->groupBy('project_id', 'pile_number')->having('total', '>', 1)->get();
        foreach ($duplicates as $duplicate) {
            $rows[] = [$duplicate->project_id, 'DUPLICATE_PILE_NUMBER:'.$duplicate->pile_number];
        }
        $this->table(['Pile/Project ID', 'Anomaly'], $rows);
        $this->line($rows === [] ? 'PASS - tidak ada anomali foundation.' : 'FAIL - '.count($rows).' anomali ditemukan; tidak ada auto-fix.');

        return $rows === [] ? self::SUCCESS : self::FAILURE;
    }
}
