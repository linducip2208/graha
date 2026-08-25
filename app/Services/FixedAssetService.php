<?php

namespace App\Services;

use App\Models\AccountingMapping;
use App\Models\AssetDepreciation;
use App\Models\FiscalPeriod;
use App\Models\FixedAsset;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FixedAssetService
{
    public function __construct(private AccountingService $accounting, private AuditTrail $audit) {}

    public function depreciate(FixedAsset $asset, FiscalPeriod $period, string $date, string $key, User $actor): AssetDepreciation
    {
        return DB::transaction(function () use ($asset, $period, $date, $key, $actor) {
            if ($existing = AssetDepreciation::where('company_id', $asset->company_id)->where('idempotency_key', $key)->first()) {
                return $existing;
            }
            $asset = FixedAsset::lockForUpdate()->findOrFail($asset->id);
            throw_unless($asset->company_id === $period->company_id && $asset->status === 'active' && $period->status === 'open', ValidationException::withMessages(['asset' => 'Aset atau periode tidak valid.']));
            throw_unless($date >= $asset->depreciation_start_date->toDateString() && $date >= $period->starts_at->toDateString() && $date <= $period->ends_at->toDateString(), ValidationException::withMessages(['date' => 'Tanggal depresiasi di luar periode/start date.']));

            $base = bcsub((string) $asset->acquisition_cost, (string) $asset->residual_value, 2);
            throw_if(bccomp($base, '0', 2) <= 0 || $asset->useful_life_months < 1, ValidationException::withMessages(['asset' => 'Basis atau umur manfaat tidak valid.']));
            $accumulated = (string) AssetDepreciation::where('fixed_asset_id', $asset->id)->sum('amount');
            $remaining = bcsub($base, $accumulated, 2);
            throw_if(bccomp($remaining, '0', 2) <= 0, ValidationException::withMessages(['asset' => 'Aset sudah terdepresiasi penuh.']));
            $monthly = bcdiv($base, (string) $asset->useful_life_months, 2);
            $amount = bccomp($monthly, $remaining, 2) === 1 ? $remaining : $monthly;

            $maps = AccountingMapping::where('company_id', $asset->company_id)->where('event_type', 'asset_depreciation')->get()->keyBy('entry_side');
            foreach (['expense_debit', 'accumulated_credit'] as $side) {
                throw_unless($maps->has($side), ValidationException::withMessages(['mapping' => "Mapping $side belum tersedia."]));
            }
            $journal = $this->accounting->post($asset->company_id, $date, 'asset_depreciation', (string) $asset->id, 'Depresiasi '.$asset->code, [
                ['account_id' => $maps['expense_debit']->account_id, 'debit' => $amount, 'credit' => '0', 'project_id' => $asset->project_id],
                ['account_id' => $maps['accumulated_credit']->account_id, 'debit' => '0', 'credit' => $amount, 'project_id' => $asset->project_id],
            ], 'asset-depreciation:'.$key, $actor);
            $depreciation = AssetDepreciation::create(['company_id' => $asset->company_id, 'fixed_asset_id' => $asset->id, 'fiscal_period_id' => $period->id, 'depreciation_date' => $date, 'amount' => $amount, 'journal_id' => $journal->id, 'posted_by' => $actor->id, 'posted_at' => now(), 'idempotency_key' => $key]);
            $this->audit->record($asset->company_id, $actor->id, 'accounting.asset_depreciated', $depreciation);

            return $depreciation;
        }, 3);
    }

    /** Disposal aset (ADR-056): hapus nilai perolehan + akumulasi, catat hasil jual, beda = gain/loss. Idempotent per key. */
    public function dispose(FixedAsset $asset, string $date, string $proceeds, string $key, User $actor): FixedAsset
    {
        return DB::transaction(function () use ($asset, $date, $proceeds, $key, $actor) {
            if ($existingJournal = Journal::where('company_id', $asset->company_id)->where('idempotency_key', 'asset-disposal:'.$key)->first()) {
                return $asset;
            }
            $asset = FixedAsset::lockForUpdate()->findOrFail($asset->id);
            throw_unless($asset->status === 'active', ValidationException::withMessages(['asset' => 'Hanya aset aktif yang dapat dilepas.']));
            throw_if(bccomp($proceeds, '0', 2) < 0, ValidationException::withMessages(['proceeds' => 'Hasil penjualan tidak boleh negatif.']));

            $accumulated = (string) AssetDepreciation::where('fixed_asset_id', $asset->id)->sum('amount');
            $bookValue = bcsub((string) $asset->acquisition_cost, $accumulated, 2);
            $gain = bccomp($proceeds, $bookValue, 2) === 1 ? bcsub($proceeds, $bookValue, 2) : '0';
            $loss = bccomp($bookValue, $proceeds, 2) === 1 ? bcsub($bookValue, $proceeds, 2) : '0';

            $maps = AccountingMapping::where('company_id', $asset->company_id)->where('event_type', 'asset_disposal')->get()->keyBy('entry_side');
            foreach (['accumulated_debit', 'asset_cost_credit'] as $side) {
                throw_unless($maps->has($side), ValidationException::withMessages(['mapping' => "Mapping asset_disposal/$side belum tersedia."]));
            }
            if (bccomp($gain, '0', 2) === 1) {
                throw_unless($maps->has('gain_credit'), ValidationException::withMessages(['mapping' => 'Mapping asset_disposal/gain_credit belum tersedia.']));
            }
            if (bccomp($loss, '0', 2) === 1) {
                throw_unless($maps->has('loss_debit'), ValidationException::withMessages(['mapping' => 'Mapping asset_disposal/loss_debit belum tersedia.']));
            }

            $lines = [
                ['account_id' => $maps['accumulated_debit']->account_id, 'debit' => $accumulated, 'credit' => '0'],
                ['account_id' => $maps['asset_cost_credit']->account_id, 'debit' => '0', 'credit' => (string) $asset->acquisition_cost],
            ];
            if (bccomp($proceeds, '0', 2) === 1) {
                throw_unless($maps->has('proceeds_debit'), ValidationException::withMessages(['mapping' => 'Mapping asset_disposal/proceeds_debit belum tersedia.']));
                $lines[] = ['account_id' => $maps['proceeds_debit']->account_id, 'debit' => $proceeds, 'credit' => '0'];
            }
            if (bccomp($gain, '0', 2) === 1) {
                $lines[] = ['account_id' => $maps['gain_credit']->account_id, 'debit' => '0', 'credit' => $gain];
            }
            if (bccomp($loss, '0', 2) === 1) {
                $lines[] = ['account_id' => $maps['loss_debit']->account_id, 'debit' => $loss, 'credit' => '0'];
            }

            $journal = $this->accounting->post($asset->company_id, $date, 'asset_disposal', (string) $asset->id, 'Disposal '.$asset->code, $lines, 'asset-disposal:'.$key, $actor);
            $asset->update(['status' => 'disposed', 'disposed_at' => $date, 'disposal_proceeds' => $proceeds, 'disposal_journal_id' => $journal->id]);
            $this->audit->record($asset->company_id, $actor->id, 'accounting.asset_disposed', $asset);

            return $asset;
        }, 3);
    }
}
