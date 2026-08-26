<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcreteDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['batch_time' => 'datetime', 'arrived_at' => 'datetime', 'pour_started_at' => 'datetime', 'pour_finished_at' => 'datetime', 'ordered_volume_m3' => 'decimal:4', 'delivered_volume_m3' => 'decimal:4', 'accepted_volume_m3' => 'decimal:4', 'rejected_volume_m3' => 'decimal:4', 'slump_cm' => 'decimal:2', 'approved_at' => 'datetime'];
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(BoredPile::class, 'bored_pile_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** Menit antara batch di plant dan tiba di lokasi (null bila data tak lengkap). */
    public function waitingMinutes(): ?int
    {
        if ($this->batch_time === null || $this->arrived_at === null) {
            return null;
        }

        return max(0, (int) round($this->batch_time->diffInMinutes($this->arrived_at, false)));
    }

    /** Durasi discharge dalam menit. */
    public function dischargeMinutes(): ?int
    {
        if ($this->pour_started_at === null || $this->pour_finished_at === null) {
            return null;
        }

        return max(0, (int) round($this->pour_started_at->diffInMinutes($this->pour_finished_at, false)));
    }

    /** Jeda dari truck sebelumnya pada pile yang sama (menit). */
    public function gapFromPreviousMinutes(): ?int
    {
        if ($this->arrived_at === null) {
            return null;
        }
        $previous = self::where('bored_pile_id', $this->bored_pile_id)
            ->where('id', '!=', $this->id)
            ->where('arrived_at', '<', $this->arrived_at)
            ->orderByDesc('arrived_at')
            ->first();
        if ($previous?->pour_finished_at === null) {
            return null;
        }

        return max(0, (int) round($previous->pour_finished_at->diffInMinutes($this->pour_started_at ?? $this->arrived_at, false)));
    }
}
