<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

class NumberSequenceService
{
    public function next(int $companyId, string $type, ?int $year = null): string
    {
        return DB::transaction(function () use ($companyId, $type, $year) {
            $year ??= (int) now()->format('Y');
            $s = NumberSequence::where('company_id', $companyId)->where('document_type', $type)->lockForUpdate()->firstOrFail();
            if ($s->reset_yearly && $s->last_reset_year !== $year) {
                $s->next_number = 1;
                $s->last_reset_year = $year;
            }$number = str_pad((string) $s->next_number, $s->padding, '0', STR_PAD_LEFT);
            $value = strtr($s->format, ['{PREFIX}' => $s->prefix ?? '', '{YYYY}' => (string) $year, '{SEQ}' => $number]);
            $s->increment('next_number');

            return $value;
        }, 3);
    }
}
