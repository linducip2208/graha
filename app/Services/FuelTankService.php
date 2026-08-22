<?php

namespace App\Services;

use App\Models\FuelTank;
use App\Models\FuelTankTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FuelTankService
{
    public function __construct(private AuditTrail $audit) {}

    /** Liters bertanda: + masuk tangki (opening/receipt/adjustment+), - keluar (issue/adjustment-). */
    public static function signed(string $type, string $liters): string
    {
        return in_array($type, ['receipt', 'opening'], true) ? $liters : bcmul($liters, '-1', 2);
    }

    public function record(FuelTank $tank, array $data, User $actor): FuelTankTransaction
    {
        return DB::transaction(function () use ($tank, $data, $actor) {
            throw_unless($actor->companies()->whereKey($tank->company_id)->where('company_user.is_active', true)->exists(), ValidationException::withMessages(['company' => 'Anda bukan anggota aktif perusahaan ini.']));
            throw_unless(in_array($data['type'], FuelTank::TYPES, true), ValidationException::withMessages(['type' => 'Jenis transaksi tidak dikenal.']));
            if ($existing = FuelTankTransaction::where('fuel_tank_id', $tank->id)->where('idempotency_key', $data['idempotency_key'])->first()) {
                return $existing;
            }
            $transaction = FuelTankTransaction::create([
                ...$data,
                'fuel_tank_id' => $tank->id,
                'liters' => self::signed($data['type'], (string) $data['liters']),
                'recorded_by' => $actor->id,
            ]);
            $this->audit->record($tank->company_id, $actor->id, 'fuel.tank_transaction', $transaction);

            return $transaction;
        }, 3);
    }

    public function balance(FuelTank $tank): string
    {
        return (string) FuelTankTransaction::where('fuel_tank_id', $tank->id)->sum('liters');
    }

    public function reconcile(FuelTank $tank, string $actualReading, User $actor): array
    {
        return DB::transaction(function () use ($tank, $actualReading, $actor) {
            throw_if(bccomp($actualReading, '0', 2) === -1, ValidationException::withMessages(['reading' => 'Pembacaan fisik tidak boleh negatif.']));
            $book = $this->balance($tank);
            $variance = bcsub($actualReading, $book, 2);
            $result = ['book' => $book, 'actual' => $actualReading, 'variance' => $variance];

            if (bccomp($variance, '0', 2) !== 0) {
                $liters = bccomp($variance, '0', 2) === 1 ? $variance : bcmul($variance, '-1', 2);
                $this->record($tank, [
                    'type' => 'reading_adjustment',
                    'occurred_at' => now(),
                    'reference' => 'reconcile',
                    'liters' => $liters,
                    'notes' => "Rekonsiliasi: buku {$book}, fisik {$actualReading}",
                    'idempotency_key' => 'recon:'.now()->format('YmdHi').':'.$tank->id.':'.str_replace('-', 'm', $liters),
                    'project_id' => null,
                    'equipment_id' => null,
                ], $actor);
                $result['adjusted'] = true;
            } else {
                $result['adjusted'] = false;
            }
            $result['book_after'] = $this->balance($tank);
            $this->audit->record($tank->company_id, $actor->id, 'fuel.tank_reconciled', $tank);

            return $result;
        }, 3);
    }
}
