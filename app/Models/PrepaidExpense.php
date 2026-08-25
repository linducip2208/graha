<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrepaidExpense extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2', 'first_period_date' => 'date', 'last_posted_period' => 'date'];
    }

    /** Nominal per bulan: pembulatan ke bawah, bulan terakhir menyerap sisa. */
    public function monthlyAmount(): string
    {
        return bcdiv((string) $this->total_amount, (string) $this->period_count, 2);
    }

    /** Bulan-bulan yang sudah lewat/di bulan ini namun belum diposting. */
    public function duePeriods(): array
    {
        $due = [];
        $cursor = $this->first_period_date->copy()->startOfMonth();
        for ($index = 0; $index < $this->period_count; $index++) {
            if ($cursor->gt(now()->startOfMonth())) {
                break;
            }
            if ($this->last_posted_period === null || $cursor->gt($this->last_posted_period->copy()->startOfMonth())) {
                $due[] = $cursor->toDateString();
            }
            $cursor->addMonthNoOverflow();
        }

        return $due;
    }

    public function finalPeriodDate(): string
    {
        return $this->first_period_date->copy()->startOfMonth()->addMonths($this->period_count - 1)->toDateString();
    }
}
